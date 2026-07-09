<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PoItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return response()->json(PurchaseOrder::with(['project', 'items.rabBudget'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'date' => 'required|date',
            'supplier_name' => 'required|string',
            'payment_terms' => 'nullable|string',
            'items' => 'required|array',
            'items.*.rab_budget_id' => 'required|exists:rab_budgets,id',
            'items.*.item_name' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $po = PurchaseOrder::create([
                'project_id' => $validated['project_id'],
                'po_number' => $validated['po_number'],
                'date' => $validated['date'],
                'supplier_name' => $validated['supplier_name'],
                'payment_terms' => $validated['payment_terms'],
                'status' => 'DRAFT',
                'created_by' => $request->user()->id ?? 1,
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

            $tax = $subtotal * 0.11; // Assuming 11% PPN
            $po->update([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $subtotal + $tax
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Draft Purchase Order (PO) berhasil dibuat.',
                'data' => $po->load('items')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat PO.', 'error' => $e->getMessage()], 500);
        }
    }
}