<?php

namespace App\Services\Rab;

use App\Services\Rab\Strategies\GikUgmStrategy;
use App\Services\Rab\Strategies\MentawaiStrategy;
use App\Services\Rab\Strategies\MuaraTewehStrategy;
use App\Services\Rab\Strategies\SekolahGorontaloStrategy;
use App\Services\Rab\Strategies\SpamJingahStrategy;
use App\Services\Rab\Strategies\WfcBaritoStrategy;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RabImportService
{
    /** @var ImportStrategyInterface[] */
    protected array $strategies = [];

    public function __construct()
    {
        // Register strategies in priority order (first match wins)
        $this->strategies = [
            new WfcBaritoStrategy(),       // Must check first (multiple sheets)
            new SpamJingahStrategy(),      // RAB - specific format, must be before GikUgmStrategy
            new GikUgmStrategy(),          // RAB GIK UGM, RAB, RAB MEP
            new SekolahGorontaloStrategy(), // RAB, RAB MEP
            new MentawaiStrategy(),        // rab
            new MuaraTewehStrategy(),      // RAB
        ];
    }

    /**
     * Get all available sheet names from an Excel file
     */
    public function getSheetNames(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $names = $spreadsheet->getSheetNames();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return $names;
    }

    /**
     * Detect the best strategy for a given spreadsheet
     */
    public function detectStrategy(Spreadsheet $spreadsheet): ?ImportStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if ($strategy->matches($sheet)) {
                    $strategy->setCurrentSheet($sheet);
                    return $strategy;
                }
            }
        }
        return null;
    }

    /**
     * Detect strategy for a specific sheet
     */
    public function detectStrategyForSheet(Spreadsheet $spreadsheet, string $sheetName): ?ImportStrategyInterface
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        foreach ($this->strategies as $strategy) {
            if ($strategy->matches($sheet)) {
                $strategy->setCurrentSheet($sheet);
                return $strategy;
            }
        }
        return null;
    }

    /**
     * Preview data from a specific sheet (first N rows)
     */
    public function previewSheet(string $filePath, string $sheetName, int $limit = 10): array
    {
        $spreadsheet = IOFactory::load($filePath);
        
        if (!$spreadsheet->sheetNameExists($sheetName)) {
            throw new \Exception("Sheet '{$sheetName}' not found");
        }

        $sheet = $spreadsheet->getSheetByName($sheetName);
        $strategy = $this->detectStrategyForSheet($spreadsheet, $sheetName);
        
        if (!$strategy) {
            throw new \Exception("No strategy found for sheet '{$sheetName}'");
        }

        $columnMap = $strategy->getColumnMap($sheet);
        $headerRow = $strategy->findHeaderRow($sheet);
        
        $rows = [];
        $count = 0;
        
        // Start from header row + 1
        for ($rowNum = $headerRow + 1; $rowNum <= $sheet->getHighestRow() && $count < $limit; $rowNum++) {
            $rowData = [];
            foreach ($columnMap as $col => $field) {
                $cell = $sheet->getCell($col . $rowNum);
                // Use getCalculatedValue() to resolve formulas
                $rowData[$col] = $cell->getCalculatedValue();
            }
            
            $parsed = $strategy->parseRow($rowData, $rowNum);
            if ($parsed) {
                $rows[] = $parsed;
                $count++;
            }
        }

        // Extract project info BEFORE disconnecting
        $projectInfo = $strategy->extractProjectInfo($sheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'sheet' => $sheetName,
            'strategy' => get_class($strategy),
            'header_row' => $headerRow,
            'column_map' => $columnMap,
            'project_info' => $projectInfo,
            'rows' => $rows,
            'total_rows' => $sheet->getHighestRow(),
        ];
    }

    /**
     * Import all data from a specific sheet
     */
    public function importSheet(string $filePath, string $sheetName, int $projectId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        
        if (!$spreadsheet->sheetNameExists($sheetName)) {
            throw new \Exception("Sheet '{$sheetName}' not found");
        }

        $sheet = $spreadsheet->getSheetByName($sheetName);
        $strategy = $this->detectStrategyForSheet($spreadsheet, $sheetName);
        
        if (!$strategy) {
            throw new \Exception("No strategy found for sheet '{$sheetName}'");
        }

        $columnMap = $strategy->getColumnMap($sheet);
        $headerRow = $strategy->findHeaderRow($sheet);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        for ($rowNum = $headerRow + 1; $rowNum <= $sheet->getHighestRow(); $rowNum++) {
            $rowData = [];
            foreach ($columnMap as $col => $field) {
                $cell = $sheet->getCell($col . $rowNum);
                // Use getCalculatedValue() to resolve formulas
                $rowData[$col] = $cell->getCalculatedValue();
            }
            
            $parsed = $strategy->parseRow($rowData, $rowNum);
            if (!$parsed) {
                $skipped++;
                continue;
            }

            // Validate total_price = qty * price
            $calculatedTotal = round($parsed['qty'] * $parsed['price'], 2);
            $actualTotal = $parsed['total'];
            
            if ($actualTotal > 0 && abs($actualTotal - $calculatedTotal) > 1) {
                $errors[] = [
                    'row' => $rowNum,
                    'description' => $parsed['description'],
                    'error' => "Total mismatch: file has {$actualTotal}, calculated {$calculatedTotal} (qty={$parsed['qty']} x price={$parsed['price']})"
                ];
            }

            // Create RabBudget record
            try {
                \App\Models\RabBudget::create([
                    'project_id' => $projectId,
                    'parent_id' => null, // Will be set if we implement category hierarchy
                    'code_item' => $parsed['code'] ?: 'ITEM-' . $rowNum,
                    'description' => $parsed['description'],
                    'unit' => $parsed['unit'] ?: 'unit',
                    'volume' => $parsed['qty'],
                    'unit_price' => $parsed['price'],
                    'total_price' => $actualTotal > 0 ? $actualTotal : $calculatedTotal,
                    'category' => $parsed['category'] ?: '',
                    'status' => 'DRAFT',
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $rowNum,
                    'description' => $parsed['description'],
                    'error' => $e->getMessage()
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import multiple sheets (for formats like WFC Barito)
     */
    public function importMultipleSheets(string $filePath, array $sheetNames, int $projectId): array
    {
        $results = [
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_errors' => 0,
            'sheets' => [],
        ];

        foreach ($sheetNames as $sheetName) {
            $result = $this->importSheet($filePath, $sheetName, $projectId);
            $results['sheets'][$sheetName] = $result;
            $results['total_imported'] += $result['imported'];
            $results['total_skipped'] += $result['skipped'];
            $results['total_errors'] += count($result['errors']);
        }

        return $results;
    }

    /**
     * Auto-detect and import (uses first matching strategy)
     */
    public function autoImport(string $filePath, int $projectId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $strategy = $this->detectStrategy($spreadsheet);
        
        if (!$strategy) {
            throw new \Exception("No matching strategy found for file");
        }

        $sheetNames = [];
        foreach ($strategy->getTargetSheetNames() as $target) {
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                if (stripos($sheetName, $target) !== false) {
                    $sheetNames[] = $sheetName;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (count($sheetNames) === 1) {
            return $this->importSheet($filePath, $sheetNames[0], $projectId);
        } else {
            return $this->importMultipleSheets($filePath, $sheetNames, $projectId);
        }
    }
}