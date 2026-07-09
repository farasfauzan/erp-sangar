<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Spk;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return response()->json(
            Invoice::with('invoiceable')->get()
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
        $validated['status'] = 'PENDING_APPROVAL';

        $invoice = Invoice::create($validated);

        return response()->json([
            'message' => 'Invoice berhasil dibuat dan menunggu persetujuan.',
            'data' => $invoice
        ], 201);
    }

    public function approveManager($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'UNPAID'; // Approved, waiting for payment
        $invoice->save();

        return response()->json([
            'message' => 'Invoice telah disetujui Manajer dan siap dibayar.',
            'data' => $invoice
        ]);
    }

    public function executePayment(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'PAID';
        $invoice->save();

        // Optionally record in general ledger here.

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat. Status Invoice: PAID.',
            'data' => $invoice
        ]);
    }
}