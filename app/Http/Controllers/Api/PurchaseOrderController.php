<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\PoAttachment;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\RabBudget;
use App\Support\WorkflowState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->query('per_page', 15), 100);

        return response()->json(PurchaseOrder::with(['project', 'items.rabBudget'])->latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'date' => 'required|date',
            'supplier_name' => 'required|string',
            'po_type' => 'nullable|string|in:PURCHASE_ORDER,REVISI,ADDENDUM',
            'addendum_number' => 'nullable|integer|min:1',
            'supplier_address' => 'nullable|string',
            'supplier_phone' => 'nullable|string',
            'supplier_contact_person' => 'nullable|string',
            'project_location' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'include_ppn' => 'nullable|boolean',
            'catatan' => 'nullable|string',
            'faktur_pajak_nama' => 'nullable|string',
            'faktur_pajak_npwp' => 'nullable|string',
            'faktur_pajak_alamat' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'items' => 'required|array',
            'items.*.rab_budget_id' => 'required|exists:rab_budgets,id',
            'items.*.item_name' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $budgetIds = collect($validated['items'])->pluck('rab_budget_id')->unique();
        $matchingBudgetCount = RabBudget::query()
            ->where('project_id', $validated['project_id'])
            ->whereIn('id', $budgetIds)
            ->count();

        if ($matchingBudgetCount !== $budgetIds->count()) {
            return response()->json([
                'message' => 'Setiap item PO harus berasal dari RAB pada proyek yang sama.',
            ], 422);
        }

        $approvedBudgetCount = RabBudget::query()
            ->where('project_id', $validated['project_id'])
            ->whereIn('id', $budgetIds)
            ->where('status', RabBudget::STATUS_APPROVED)
            ->count();

        if ($approvedBudgetCount !== $budgetIds->count()) {
            return response()->json([
                'message' => 'PO hanya dapat dibuat dari item RAB yang sudah disetujui.',
            ], 422);
        }

        try {
            $po = DB::transaction(function () use ($validated, $request) {
                $po = PurchaseOrder::create([
                    'project_id' => $validated['project_id'],
                    'po_number' => $validated['po_number'],
                    'date' => $validated['date'],
                    'supplier_name' => $validated['supplier_name'],
                    'po_type' => $validated['po_type'] ?? 'PURCHASE_ORDER',
                    'addendum_number' => $validated['addendum_number'] ?? null,
                    'supplier_address' => $validated['supplier_address'] ?? null,
                    'supplier_phone' => $validated['supplier_phone'] ?? null,
                    'supplier_contact_person' => $validated['supplier_contact_person'] ?? null,
                    'project_location' => $validated['project_location'] ?? null,
                    'discount' => $validated['discount'] ?? 0,
                    'include_ppn' => $validated['include_ppn'] ?? true,
                    'catatan' => $validated['catatan'] ?? null,
                    'faktur_pajak_nama' => $validated['faktur_pajak_nama'] ?? 'PT. SINAR CERAH SEMPURNA',
                    'faktur_pajak_npwp' => $validated['faktur_pajak_npwp'] ?? '002.652.984.2-331.000',
                    'faktur_pajak_alamat' => $validated['faktur_pajak_alamat'] ?? 'Karangrejo Barat No. 9 RT 002 RW 002, Tinjomoyo, Banyumanik, Semarang',
                    'payment_terms' => $validated['payment_terms'],
                    'status' => 'DRAFT',
                    'created_by' => $request->user()->id,
                ]);

                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $totalPrice = $item['qty'] * $item['unit_price'];
                    $subtotal += $totalPrice;

                    PoItem::create([
                        'purchase_order_id' => $po->id,
                        'rab_budget_id' => $item['rab_budget_id'],
                        'item_name' => $item['item_name'],
                        'qty' => $item['qty'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $totalPrice,
                    ]);
                }

                $discount = $validated['discount'] ?? 0;
                $subtotalAfterDiscount = $subtotal - $discount;
                $includePpn = $validated['include_ppn'] ?? true;
                $tax = $includePpn ? $subtotalAfterDiscount * 0.11 : 0;
                $po->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'total_amount' => $subtotalAfterDiscount + $tax,
                ]);

                return $po;
            });

            return response()->json([
                'message' => 'Draft Purchase Order (PO) berhasil dibuat.',
                'data' => $po->load('items'),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal membuat PO.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $po = PurchaseOrder::with(['items.rabBudget', 'project', 'attachments.uploader'])->findOrFail($id);
        return response()->json($po);
    }

    public function submit(Request $request, $id)
    {
        $po = DB::transaction(function () use ($request, $id) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($id);
            WorkflowState::require(
                $po->status,
                ['DRAFT'],
                'Hanya PO berstatus DRAFT yang dapat dikirim untuk approval.'
            );
            $po->update(['status' => 'PENDING_APPROVAL']);
            $this->log($request, $po, 'SUBMIT');

            return $po;
        });

        return response()->json(['message' => 'PO dikirim untuk approval.', 'data' => $po]);
    }

    public function approve(Request $request, $id)
    {
        $po = DB::transaction(function () use ($request, $id) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($id);
            WorkflowState::require(
                $po->status,
                ['PENDING_APPROVAL'],
                'PO harus berstatus PENDING_APPROVAL sebelum disetujui.'
            );
            $po->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
            ]);
            $this->log($request, $po, 'APPROVE');

            return $po;
        });

        return response()->json(['message' => 'PO disetujui.', 'data' => $po]);
    }

    public function reject(Request $request, $id)
    {
        $po = DB::transaction(function () use ($request, $id) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($id);
            WorkflowState::require(
                $po->status,
                ['PENDING_APPROVAL'],
                'PO harus berstatus PENDING_APPROVAL sebelum ditolak.'
            );
            $po->update(['status' => 'REJECTED']);
            $this->log($request, $po, 'REJECT', $request->input('notes'));

            return $po;
        });

        return response()->json(['message' => 'PO ditolak.', 'data' => $po]);
    }

    public function uploadAttachment(Request $request, $poId)
    {
        $po = PurchaseOrder::findOrFail($poId);

        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,xlsx',
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store("attachments/po/{$po->id}", 'public');

        $attachment = PoAttachment::create([
            'purchase_order_id' => $po->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'File berhasil diunggah.',
            'data' => $attachment->load('uploader'),
        ], 201);
    }

    public function deleteAttachment(PoAttachment $attachment)
    {
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json(['message' => 'File berhasil dihapus.']);
    }

    public function getAttachments($poId)
    {
        $po = PurchaseOrder::findOrFail($poId);
        $attachments = $po->attachments()->with('uploader')->latest()->get();

        return response()->json($attachments);
    }

    private function log(Request $request, PurchaseOrder $po, string $action, ?string $notes = null): void
    {
        ApprovalLog::create([
            'record_type' => PurchaseOrder::class,
            'record_id' => $po->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
