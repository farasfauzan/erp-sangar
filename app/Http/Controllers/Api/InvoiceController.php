<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Spk;
use App\Models\Transaction;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return response()->json(
            Invoice::with(['invoiceable', 'transactions'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoiceable_type' => 'required|string',
            'invoiceable_id'   => 'required|integer',
            'invoice_number'   => 'required|string|unique:invoices,invoice_number',
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
        ]);

        // Auto-calculate amount based on reference document
        $model = null;
        $amount = 0;
        
        if ($validated['invoiceable_type'] === 'App\Models\PurchaseOrder') {
            $model = PurchaseOrder::findOrFail($validated['invoiceable_id']);
            $amount = $model->total_amount;
        } elseif ($validated['invoiceable_type'] === 'App\Models\Spk') {
            $model = Spk::findOrFail($validated['invoiceable_id']);
            // If it's SPK, theoretically we should grab the specific Opname amount. 
            // For simplicity in this stage, we assume they are billing the full SPK or we let them override.
            // Let's just use total_amount as default if no specific amount is provided.
            $amount = $request->input('amount', $model->total_amount); 
        }

        if (!$model) {
            return response()->json(['message' => 'Dokumen referensi tidak ditemukan.'], 404);
        }

        $validated['amount'] = $amount;
        $validated['status'] = 'PENDING_ENGINEER';

        $invoice = Invoice::create($validated);

        return response()->json([
            'message' => 'Invoice berhasil dibuat dan menunggu verifikasi engineer.',
            'data' => $invoice
        ], 201);
    }

    public function verifyEngineer(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => 'ENGINEER_VERIFIED']);
        $this->log($request, $invoice, 'ENGINEER_VERIFY');

        return response()->json(['message' => 'Invoice lolos verifikasi engineer.', 'data' => $invoice]);
    }

    public function verifyFinance(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => 'PENDING_APPROVAL']);
        $this->log($request, $invoice, 'FINANCE_VERIFY');

        return response()->json(['message' => 'Invoice lolos verifikasi finance dan menunggu approval manajer.', 'data' => $invoice]);
    }

    public function approveManager(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'UNPAID'; // Approved, waiting for payment
        $invoice->save();
        $this->log($request, $invoice, 'MANAGER_APPROVE');

        return response()->json([
            'message' => 'Invoice telah disetujui Manajer dan siap dibayar.',
            'data' => $invoice
        ]);
    }

    public function executePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'proof_of_payment' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'PAID';
        $invoice->save();

        Transaction::create([
            'invoice_id' => $invoice->id,
            'payment_method' => $validated['payment_method'],
            'amount' => $validated['amount'] ?? $invoice->amount,
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'proof_of_payment' => $validated['proof_of_payment'] ?? null,
        ]);

        $this->log($request, $invoice, 'PAYMENT');

        return response()->json([
            'message' => 'Pembayaran dan bukti bayar berhasil dicatat. Status Invoice: PAID.',
            'data' => $invoice->load('transactions')
        ]);
    }

    private function log(Request $request, Invoice $invoice, string $action): void
    {
        ApprovalLog::create([
            'record_type' => Invoice::class,
            'record_id' => $invoice->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => $action,
        ]);
    }
}