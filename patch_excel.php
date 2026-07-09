<?php

$controllersDir = __DIR__ . '/app/Http/Controllers/Api/';
$importsDir = __DIR__ . '/app/Imports/';
if (!is_dir($importsDir)) {
    mkdir($importsDir, 0755, true);
}

$rabImportCode = <<<'PHP'
<?php

namespace App\Imports;

use App\Models\RabBudget;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class RabImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    protected $projectId;

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
    }

    public function model(array $row)
    {
        // Skip empty rows
        if (!isset($row['uraian_pekerjaan']) && !isset($row['description'])) {
            return null;
        }

        return new RabBudget([
            'project_id'  => $this->projectId,
            'code_item'   => $row['kode_item'] ?? $row['code'] ?? null,
            'description' => $row['uraian_pekerjaan'] ?? $row['description'] ?? 'Tanpa Deskripsi',
            'unit'        => $row['satuan'] ?? $row['unit'] ?? 'LS',
            'volume'      => isset($row['volume']) ? floatval(str_replace(',', '.', $row['volume'])) : 0,
            'unit_price'  => isset($row['harga_satuan']) ? floatval(str_replace(',', '.', $row['harga_satuan'])) : 0,
            'total_price' => isset($row['total_harga']) ? floatval(str_replace(',', '.', $row['total_harga'])) : 0,
            'category'    => $row['kategori'] ?? $row['category'] ?? null,
        ]);
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
PHP;

file_put_contents($importsDir . 'RabImport.php', $rabImportCode);
echo "Created RabImport.php\n";

$rabBudgetControllerCode = <<<'PHP'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\RabImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\RabBudget;
use App\Models\Project;

class RabBudgetController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'file' => 'required|mimes:xlsx,xls,csv|max:20480', // Max 20MB
        ]);

        $projectId = $request->project_id;
        
        // Optional: clear existing budget for this project before re-importing
        if ($request->boolean('overwrite')) {
            RabBudget::where('project_id', $projectId)->delete();
        }

        try {
            Excel::import(new RabImport($projectId), $request->file('file'));
            
            return response()->json([
                'message' => 'Data RAB berhasil diimpor.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengimpor data RAB. Pastikan format kolom benar.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request, $projectId)
    {
        $budgets = RabBudget::where('project_id', $projectId)->paginate(50);
        return response()->json($budgets);
    }
}
PHP;

file_put_contents($controllersDir . 'RabBudgetController.php', $rabBudgetControllerCode);
echo "Created RabBudgetController.php\n";

// Update routes/api.php
$routesFile = __DIR__ . '/routes/api.php';
$routesContent = file_get_contents($routesFile);
if (!str_contains($routesContent, 'RabBudgetController')) {
    $routesContent = str_replace(
        "use App\Http\Controllers\Api\InvoiceController;",
        "use App\Http\Controllers\Api\InvoiceController;\nuse App\Http\Controllers\Api\RabBudgetController;",
        $routesContent
    );
    $routesContent .= "\n// RAB Data\nRoute::post('/rab/import', [RabBudgetController::class, 'import']);\nRoute::get('/projects/{projectId}/rab', [RabBudgetController::class, 'index']);\n";
    file_put_contents($routesFile, $routesContent);
    echo "Updated routes/api.php\n";
}

