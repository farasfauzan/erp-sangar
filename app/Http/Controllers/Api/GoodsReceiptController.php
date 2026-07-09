<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function index()
    {
        return response()->json(
            GoodsReceipt::with(['purchaseOrder.project', 'purchaseOrder.items'])->get()
        );
    }

    public function getByPo($poId)
    {
        return response()->json(
            GoodsReceipt::where('purchase_order_id', $poId)->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_number'    => 'required|string|unique:goods_receipts,receipt_number',
            'receipt_date'      => 'required|date',
            'delivery_note_number' => 'nullable|string',
            'receiver_name'     => 'required|string',
            'notes'             => 'nullable|string',
        ]);

        $gr = GoodsReceipt::create($validated);

        // Update PO status to indicate goods have been received
        $po = PurchaseOrder::find($validated['purchase_order_id']);
        if ($po && $po->status === 'APPROVED') {
            $po->update(['status' => 'RECEIVED']);
        }

        return response()->json([
            'message' => 'Penerimaan Barang berhasil dicatat.',
            'data' => $gr->load('purchaseOrder')
        ], 201);
    }
}