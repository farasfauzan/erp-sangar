<?php

$controllersDir = __DIR__ . '/app/Http/Controllers/Api/';
if (!is_dir($controllersDir)) mkdir($controllersDir, 0755, true);

$poControllerCode = <<<'PHP'
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
PHP;

$spkControllerCode = <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spk;
use Illuminate\Http\Request;

class SpkController extends Controller
{
    public function index()
    {
        return response()->json(Spk::with(['project'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'spk_number' => 'required|string|unique:spks,spk_number',
            'subcon_name' => 'required|string',
            'subtotal' => 'required|numeric',
            'payment_terms' => 'nullable|string',
        ]);

        $tax = $validated['subtotal'] * 0.11;

        $spk = Spk::create([
            'project_id' => $validated['project_id'],
            'spk_number' => $validated['spk_number'],
            'subcon_name' => $validated['subcon_name'],
            'subtotal' => $validated['subtotal'],
            'tax_amount' => $tax,
            'total_amount' => $validated['subtotal'] + $tax,
            'payment_terms' => $validated['payment_terms'],
            'status' => 'DRAFT',
            'created_by' => $request->user()->id ?? 1,
        ]);

        return response()->json([
            'message' => 'Draft Surat Perintah Kerja (SPK) berhasil dibuat.',
            'data' => $spk
        ], 201);
    }
}
PHP;

file_put_contents($controllersDir . 'PurchaseOrderController.php', $poControllerCode);
echo "Created PurchaseOrderController.php\n";

file_put_contents($controllersDir . 'SpkController.php', $spkControllerCode);
echo "Created SpkController.php\n";

// Update routes/api.php
$routesFile = __DIR__ . '/routes/api.php';
$routesContent = file_get_contents($routesFile);
if (!str_contains($routesContent, 'PurchaseOrderController')) {
    $routesContent = str_replace(
        "use App\Http\Controllers\Api\RabBudgetController;",
        "use App\Http\Controllers\Api\RabBudgetController;\nuse App\Http\Controllers\Api\PurchaseOrderController;\nuse App\Http\Controllers\Api\SpkController;",
        $routesContent
    );
    $routesContent .= "\n// Workflow A: Pengadaan & Kontrak\nRoute::get('/pos', [PurchaseOrderController::class, 'index']);\nRoute::post('/pos', [PurchaseOrderController::class, 'store']);\n";
    $routesContent .= "Route::get('/spks', [SpkController::class, 'index']);\nRoute::post('/spks', [SpkController::class, 'store']);\n";
    file_put_contents($routesFile, $routesContent);
    echo "Updated routes/api.php\n";
}

