<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\GoodsReceipt;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryStock::query();

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($search = $request->get('search')) {
            $query->where('item_name', 'like', "%{$search}%");
        }

        // Low stock filter
        if ($request->boolean('low_stock')) {
            $query->whereColumn('qty', '<', 'min_qty');
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('item_name')->paginate($request->get('per_page', 50)),
        ]);
    }

    public function receive(Request $request)
    {
        $request->validate([
            'project_id'      => 'required|exists:projects,id',
            'item_name'       => 'required|string|max:255',
            'unit'            => 'nullable|string|max:50',
            'qty'             => 'required|numeric|min:0.001',
            'warehouse'       => 'nullable|string|max:255',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
        ]);

        $stock = InventoryStock::firstOrCreate(
            [
                'project_id' => $request->project_id,
                'item_name'  => $request->item_name,
                'warehouse'  => $request->warehouse ?? 'Main',
            ],
            ['unit' => $request->unit ?? 'Pcs', 'qty' => 0, 'min_qty' => 0]
        );

        $stock->increment('qty', $request->qty);

        // Link to GR if provided
        if ($request->goods_receipt_id) {
            $gr = GoodsReceipt::find($request->goods_receipt_id);
            if ($gr) {
                $gr->update(['po_id' => $gr->po_id]); // noop — model already has observer for GR
            }
        }

        return response()->json([
            'success' => true,
            'data' => $stock,
            'message' => "Stok {$request->item_name} bertambah {$request->qty} {$stock->unit}",
        ]);
    }
}