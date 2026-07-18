<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->query('per_page', 15), 100);
        $projects = Project::select('id', 'project_name', 'location', 'start_date', 'status')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    public function show($id)
    {
        $project = Project::with(['rabBudgets' => function ($q) {
            $q->select('id', 'project_id', 'code_item', 'description', 'volume', 'unit_price', 'total_price', 'category', 'status', 'version')
                ->where('status', '!=', 'ARCHIVED')
                ->latest('version');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'start_date' => 'required|date',
        ]);

        $project = Project::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek baru berhasil dibuat.',
            'data' => $project,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'project_name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'start_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|in:planning,active,completed,on_hold,cancelled',
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil diperbarui.',
            'data' => $project->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyek berhasil dihapus.',
        ]);
    }

    public function resetData($id)
    {
        $project = Project::findOrFail($id);

        DB::transaction(function () use ($id) {
            // Because SQLite cascading might not be fully active by default in all environments,
            // we will delete from dependent tables explicitly where necessary to avoid orphan data.
            
            // Delete invoice details
            if (Schema::hasTable('invoice_items')) {
                DB::table('invoice_items')->whereIn('invoice_id', function ($q) use ($id) {
                    $q->select('id')->from('invoices')->whereIn('invoiceable_id', function ($sq) use ($id) {
                        $sq->select('id')->from('purchase_orders')->where('project_id', $id);
                    })->where('invoiceable_type', \App\Models\PurchaseOrder::class);
                })->delete();

                DB::table('invoice_items')->whereIn('invoice_id', function ($q) use ($id) {
                    $q->select('id')->from('invoices')->whereIn('invoiceable_id', function ($sq) use ($id) {
                        $sq->select('id')->from('spks')->where('project_id', $id);
                    })->where('invoiceable_type', \App\Models\Spk::class);
                })->delete();
            }
            
            if (Schema::hasTable('invoices')) {
                DB::table('invoices')->whereIn('invoiceable_id', function ($sq) use ($id) {
                    $sq->select('id')->from('purchase_orders')->where('project_id', $id);
                })->where('invoiceable_type', \App\Models\PurchaseOrder::class)->delete();
                
                DB::table('invoices')->whereIn('invoiceable_id', function ($sq) use ($id) {
                    $sq->select('id')->from('spks')->where('project_id', $id);
                })->where('invoiceable_type', \App\Models\Spk::class)->delete();
            }

            if (Schema::hasTable('goods_receipt_items')) {
                DB::table('goods_receipt_items')->whereIn('goods_receipt_id', function($q) use ($id) {
                    $q->select('id')->from('goods_receipts')->whereIn('purchase_order_id', function($sq) use ($id) {
                        $sq->select('id')->from('purchase_orders')->where('project_id', $id);
                    });
                })->delete();
            }
            
            if (Schema::hasTable('goods_receipts')) {
                DB::table('goods_receipts')->whereIn('purchase_order_id', function($q) use ($id) {
                    $q->select('id')->from('purchase_orders')->where('project_id', $id);
                })->delete();
            }
            
            if (Schema::hasTable('bast_items')) {
                DB::table('bast_items')->whereIn('bast_id', function($q) use ($id) {
                    $q->select('id')->from('basts')->whereIn('spk_id', function($sq) use ($id) {
                        $sq->select('id')->from('spks')->where('project_id', $id);
                    });
                })->delete();
            }
            
            if (Schema::hasTable('basts')) {
                DB::table('basts')->whereIn('spk_id', function($q) use ($id) {
                    $q->select('id')->from('spks')->where('project_id', $id);
                })->delete();
            }

            // `po_items` is the actual table name. These rows use a required
            // foreign key to purchase_orders, so they must be removed before
            // the parent PO can be reset.
            if (Schema::hasTable('po_items')) {
                DB::table('po_items')->whereIn('purchase_order_id', function($q) use ($id) {
                    $q->select('id')->from('purchase_orders')->where('project_id', $id);
                })->delete();
            }
            if (Schema::hasTable('po_attachments')) {
                DB::table('po_attachments')->whereIn('purchase_order_id', function($q) use ($id) {
                    $q->select('id')->from('purchase_orders')->where('project_id', $id);
                })->delete();
            }
            
            if (Schema::hasTable('spk_items')) {
                DB::table('spk_items')->whereIn('spk_id', function($q) use ($id) {
                    $q->select('id')->from('spks')->where('project_id', $id);
                })->delete();
            }
            if (Schema::hasTable('spk_attachments')) {
                DB::table('spk_attachments')->whereIn('spk_id', function($q) use ($id) {
                    $q->select('id')->from('spks')->where('project_id', $id);
                })->delete();
            }

            // Clear direct children of projects
            $tables = [
                'purchase_requisitions',
                'material_requests',
                'efakturs',
                'general_ledgers',
                'rab_import_jobs',
                'fund_requests',
                'spks',
                'purchase_orders',
                'rab_budgets',
            ];

            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('project_id', $id)->delete();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data transaksi proyek berhasil di-reset.',
        ]);
    }
}
