<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\RabImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\RabBudget;
use App\Models\Project;
use App\Imports\RabPreviewImport;

class RabBudgetController extends Controller
{
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:20480',
        ]);

        try {
            $importer = new RabPreviewImport();
            Excel::import($importer, $request->file('file'));
            
            return response()->json([
                'rows' => $importer->data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membaca preview Excel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        // Increase limits for large Excel files
        ini_set('memory_limit', '2G');
        set_time_limit(300);

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'file' => 'required|mimes:xlsx,xls,csv|max:20480', // Max 20MB
            'header_row' => 'required|integer|min:0',
            'mapping' => 'required|json'
        ]);

        $projectId = $request->project_id;
        $headerRow = $request->header_row;
        $mapping = json_decode($request->mapping, true);
        
        // Optional: clear existing budget for this project before re-importing
        if ($request->boolean('overwrite')) {
            RabBudget::where('project_id', $projectId)->delete();
        }

        try {
            Excel::import(new RabImport($projectId, $headerRow, $mapping), $request->file('file'));
            
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