<?php

$controllersDir = __DIR__ . '/app/Http/Controllers/Api/';
$routesApiFile = __DIR__ . '/routes/api.php';

$controllersCode = [
    'GoodsReceiptController' => <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function store(Request $request, $projectId)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_number' => 'required|string',
            'receipt_date' => 'required|date',
        ]);
        
        $validated['project_id'] = $projectId;
        $validated['received_by'] = $request->user()->name ?? 'Logistik';

        $goodsReceipt = GoodsReceipt::create($validated);

        return response()->json([
            'message' => 'Surat Jalan berhasil diinput.',
            'data' => $goodsReceipt
        ], 201);
    }
}
PHP,
    'OpnameController' => <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opname;
use Illuminate\Http\Request;

class OpnameController extends Controller
{
    public function store(Request $request, $projectId)
    {
        $validated = $request->validate([
            'spk_id' => 'required|exists:spks,id',
            'opname_date' => 'required|date',
            'progress_percentage' => 'required|numeric|min:0|max:100',
        ]);
        
        $validated['project_id'] = $projectId;
        $validated['checked_by'] = $request->user()->name ?? 'Lapangan';

        $opname = Opname::create($validated);

        return response()->json([
            'message' => 'Opname berhasil diinput.',
            'data' => $opname
        ], 201);
    }
}
PHP,
    'InvoiceController' => <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ApprovalLog;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_type' => 'required|in:PO,SPK',
            'reference_id' => 'required|integer',
            'invoice_number' => 'required|string',
            'amount_due' => 'required|numeric',
        ]);
        
        $invoice = new Invoice();
        if ($validated['reference_type'] === 'PO') {
            $invoice->purchase_order_id = $validated['reference_id'];
        } else {
            $invoice->spk_id = $validated['reference_id'];
        }
        $invoice->invoice_number = $validated['invoice_number'];
        $invoice->invoice_date = now();
        $invoice->amount_due = $validated['amount_due'];
        $invoice->status = 'DRAFT';
        $invoice->save();

        return response()->json([
            'message' => 'Invoice berhasil dibuat.',
            'data' => $invoice
        ], 201);
    }

    public function verifyEngineer(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'ENGINEER_VERIFIED';
        $invoice->save();

        ApprovalLog::create([
            'record_type' => 'Invoice',
            'record_id' => $invoice->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => 'ENGINEER_VERIFY',
            'notes' => $request->notes ?? ''
        ]);

        return response()->json(['message' => 'Invoice diverifikasi Engineer.', 'data' => $invoice]);
    }

    public function verifyFinance(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'FINANCE_VERIFIED';
        $invoice->tax_amount = $request->tax_amount ?? 0;
        $invoice->save();

        ApprovalLog::create([
            'record_type' => 'Invoice',
            'record_id' => $invoice->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => 'FINANCE_VERIFY',
            'notes' => $request->notes ?? ''
        ]);

        return response()->json(['message' => 'Invoice diverifikasi Keuangan.', 'data' => $invoice]);
    }

    public function approveManager(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'MGR_APPROVED';
        $invoice->save();

        ApprovalLog::create([
            'record_type' => 'Invoice',
            'record_id' => $invoice->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => 'MGR_APPROVE',
            'notes' => $request->notes ?? ''
        ]);

        return response()->json(['message' => 'Invoice di-approve Manager.', 'data' => $invoice]);
    }

    public function executePayment(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'PAID';
        $invoice->save();

        ApprovalLog::create([
            'record_type' => 'Invoice',
            'record_id' => $invoice->id,
            'user_id' => $request->user()->id ?? 1,
            'action' => 'PAYMENT_EXECUTED',
            'notes' => $request->notes ?? ''
        ]);

        return response()->json(['message' => 'Invoice berhasil dibayar.', 'data' => $invoice]);
    }
}
PHP,
];

foreach ($controllersCode as $filename => $code) {
    file_put_contents($controllersDir . $filename . '.php', $code);
    echo "Wrote $filename\n";
}

$routesApiCode = <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\OpnameController;
use App\Http\Controllers\Api\InvoiceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Workflow C: Goods Receipt & Opname
Route::post('/projects/{projectId}/goods-receipts', [GoodsReceiptController::class, 'store']);
Route::post('/projects/{projectId}/opnames', [OpnameController::class, 'store']);

// Workflow C: Invoices
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::put('/invoices/{id}/engineer-verify', [InvoiceController::class, 'verifyEngineer']);
Route::put('/invoices/{id}/finance-verify', [InvoiceController::class, 'verifyFinance']);
Route::put('/invoices/{id}/manager-approve', [InvoiceController::class, 'approveManager']);
Route::post('/invoices/{id}/payments', [InvoiceController::class, 'executePayment']);
PHP;

file_put_contents($routesApiFile, $routesApiCode);
echo "Wrote routes/api.php\n";

