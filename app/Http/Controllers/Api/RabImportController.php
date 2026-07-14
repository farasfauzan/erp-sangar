<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Rab\RabImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RabImportController extends Controller
{
    protected RabImportService $importService;

    public function __construct(RabImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Upload Excel file and return sheet names
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $file = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('rab-imports', $filename, 'local');

        $fullPath = Storage::disk('local')->path($path);
        $sheetNames = $this->importService->getSheetNames($fullPath);

        return response()->json([
            'file_id' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'sheets' => $sheetNames,
        ]);
    }

    /**
     * Preview data from a specific sheet
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'sheet' => 'required|string',
        ]);

        $path = Storage::disk('local')->path('rab-imports/' . $request->file_id);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            $preview = $this->importService->previewSheet($path, $request->sheet);
            return response()->json($preview);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Validate import data (check total_price = qty * price)
     */
    public function validateImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'sheet' => 'required|string',
        ]);

        $path = Storage::disk('local')->path('rab-imports/' . $request->file_id);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            $preview = $this->importService->previewSheet($path, $request->sheet, 100);
            
            $errors = [];
            foreach ($preview['rows'] as $row) {
                $calculated = round($row['qty'] * $row['price'], 2);
                $actual = $row['total'];
                
                if ($actual > 0 && abs($actual - $calculated) > 1) {
                    $errors[] = [
                        'row' => $row['row_number'],
                        'description' => $row['description'],
                        'error' => "Total mismatch: file has {$actual}, calculated {$calculated} (qty={$row['qty']} × price={$row['price']})"
                    ];
                }
            }

            return response()->json([
                'valid' => empty($errors),
                'checked_rows' => count($preview['rows']),
                'errors' => $errors,
                'project_info' => $preview['project_info'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Import RAB data to project
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'sheet' => 'required|string',
            'project_id' => 'required|exists:projects,id',
        ]);

        $path = Storage::disk('local')->path('rab-imports/' . $request->file_id);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            $result = $this->importService->importSheet($path, $request->sheet, $request->project_id);
            
            // Clean up temp file
            Storage::disk('local')->delete('rab-imports/' . $request->file_id);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Auto-detect and import (for files with single sheet)
     */
    public function autoImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'project_id' => 'required|exists:projects,id',
        ]);

        $path = Storage::disk('local')->path('rab-imports/' . $request->file_id);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            $result = $this->importService->autoImport($path, $request->project_id);
            
            // Clean up temp file
            Storage::disk('local')->delete('rab-imports/' . $request->file_id);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * List all projects for dropdown
     */
    public function projects(): JsonResponse
    {
        $projects = Project::select('id', 'project_name', 'project_code')->get();
        return response()->json($projects);
    }
}