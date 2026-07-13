<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryStock::with('rabBudget');

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($search = $request->get('search')) {
            $query->where('item_name', 'like', "%{$search}%");
        }

        // Low stock filter
        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'min_quantity');
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
            'rab_budget_id'   => 'nullable|exists:rab_budgets,id',
            'item_name'       => 'required|string|max:255',
            'unit'            => 'nullable|string|max:50',
            'quantity'        => 'required|numeric|min:0.001',
            'location'        => 'nullable|string|max:255',
        ]);

        $identity = ['project_id' => $request->project_id];
        if ($request->rab_budget_id) {
            $identity['rab_budget_id'] = $request->rab_budget_id;
        } else {
            $identity['item_name'] = $request->item_name;
            $identity['location'] = $request->location ?? 'Main';
        }

        $stock = InventoryStock::firstOrCreate($identity, [
            'item_name' => $request->item_name,
            'unit' => $request->unit ?? 'Pcs',
            'quantity' => 0,
            'min_quantity' => 0,
            'location' => $request->location ?? 'Main',
        ]);

        $stock->increment('quantity', $request->quantity);

        return response()->json([
            'success' => true,
            'data' => $stock,
            'message' => "Stok {$request->item_name} bertambah {$request->quantity} {$stock->unit}",
        ]);
    }

    /**
     * List stock movements for an inventory item.
     */
    public function movements(Request $request, InventoryStock $stock)
    {
        $movements = StockMovement::where('inventory_stock_id', $stock->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    /**
     * Manual stock adjustment with reason.
     */
    public function adjust(Request $request, InventoryStock $stock)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'notes'    => 'required|string',
        ]);

        DB::transaction(function () use ($request, $stock) {
            StockMovement::create([
                'inventory_stock_id' => $stock->id,
                'type'               => 'adjustment',
                'quantity'           => $request->quantity,
                'notes'              => $request->notes,
                'created_by'         => Auth::id(),
            ]);

            $stock->increment('quantity', $request->quantity);
        });

        $stock->refresh();

        return response()->json([
            'success' => true,
            'data'    => $stock,
            'message' => "Stok {$stock->item_name} disesuaikan sebanyak {$request->quantity} {$stock->unit}",
        ]);
    }
}
