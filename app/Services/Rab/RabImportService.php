<?php

namespace App\Services\Rab;

use App\Services\Rab\Strategies\GikUgmStrategy;
use App\Services\Rab\Strategies\MentawaiStrategy;
use App\Services\Rab\Strategies\MuaraTewehStrategy;
use App\Services\Rab\Strategies\SekolahGorontaloStrategy;
use App\Services\Rab\Strategies\SpamJingahStrategy;
use App\Services\Rab\Strategies\WfcBaritoStrategy;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
        // listWorksheetNames reads workbook metadata without materialising all
        // cells, which is important for large RAB workbooks.
        $reader = IOFactory::createReaderForFile($filePath);
        return $reader->listWorksheetNames($filePath);
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

        // Prefer an exact sheet-name match across all strategies. Without
        // this pass, the generic target "RAB" can incorrectly claim sheets
        // such as "RAB GIK UGM" before its dedicated strategy is checked.
        foreach ($this->strategies as $strategy) {
            foreach ($strategy->getTargetSheetNames() as $targetName) {
                if (strcasecmp(trim($sheetName), trim($targetName)) === 0) {
                    $strategy->setCurrentSheet($sheet);
                    return $strategy;
                }
            }
        }

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
        $limit = max(1, min($limit, 10000));

        // First read only the header area so strategy detection does not load
        // the entire workbook into memory.
        $headerBook = $this->loadSheetRange($filePath, $sheetName, 1, 30);
        if (!$headerBook->sheetNameExists($sheetName)) {
            throw new \Exception("Sheet '{$sheetName}' not found");
        }

        $headerSheet = $headerBook->getSheetByName($sheetName);
        $headerStrategy = $this->detectStrategyForSheet($headerBook, $sheetName);
        if (!$headerStrategy) {
            $headerBook->disconnectWorksheets();
            unset($headerBook, $headerSheet);

            // A workbook can contain recap, schedule, analysis, or helper
            // sheets that are useful to review even though they are not RAB
            // item sheets. Return their original cells instead of rejecting
            // them with "No strategy found".
            $spreadsheet = $this->loadSheetRange($filePath, $sheetName, 1, $limit);
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $highestLoadedRow = min($sheet->getHighestRow(), $limit);
            [$rawColumns, $rawRows] = $this->extractRawPreview($sheet, $highestLoadedRow);
            $totalRows = $this->worksheetTotalRows($filePath, $sheetName, $highestLoadedRow);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'sheet' => $sheetName,
                'strategy' => null,
                'header_row' => null,
                'column_map' => [],
                'project_info' => [],
                'rows' => [],
                'raw_columns' => $rawColumns,
                'raw_rows' => $rawRows,
                'total_rows' => $totalRows,
            ];
        }

        $headerRow = $headerStrategy->findHeaderRow($headerSheet);
        $projectInfo = $headerStrategy->extractProjectInfo($headerSheet);
        $headerBook->disconnectWorksheets();
        unset($headerBook, $headerSheet, $headerStrategy);

        // Keep the header in the filtered read because BaseImportStrategy
        // derives its column map from the worksheet itself.
        $spreadsheet = $this->loadSheetRange($filePath, $sheetName, 1, $headerRow + $limit);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $strategy = $this->detectStrategyForSheet($spreadsheet, $sheetName);
        if (!$strategy) {
            throw new \Exception("No strategy found for sheet '{$sheetName}'");
        }

        $columnMap = $strategy->getColumnMap($sheet);
        $rows = [];
        $count = 0;

        // Start from header row + 1. The read filter bounds the number of
        // physical rows held in memory while preserving the existing parser.
        $highestLoadedRow = min($sheet->getHighestRow(), $headerRow + $limit);
        for ($rowNum = $headerRow + 1; $rowNum <= $highestLoadedRow && $count < $limit; $rowNum++) {
            $rowData = [];
            foreach ($columnMap as $col => $field) {
                $cell = $sheet->getCell($col . $rowNum);
                $value = $cell->getValue();
                // Reuse the result Excel stored in the workbook instead of
                // recalculating formulas and their cross-sheet references.
                // This keeps preview fast while retaining the displayed
                // numeric value for formula-based prices and totals.
                if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    $cachedValue = $cell->getOldCalculatedValue();
                    if ($cachedValue !== null) {
                        $value = $cachedValue;
                    }
                }
                $rowData[$col] = $value;
            }
            
            $parsed = $strategy->parseRow($rowData, $rowNum);
            if ($parsed) {
                $rows[] = $parsed;
                $count++;
            }
        }

        // Keep the original sheet values for an Excel-like visual preview.
        // The import parser still uses only the mapped business columns, but
        // users can see project headers, category rows, and every populated
        // row exactly in its original worksheet position.
        [$rawColumns, $rawRows] = $this->extractRawPreview($sheet, $highestLoadedRow);
        $totalRows = $this->worksheetTotalRows($filePath, $sheetName, $highestLoadedRow);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'sheet' => $sheetName,
            'strategy' => get_class($strategy),
            'header_row' => $headerRow,
            'column_map' => $columnMap,
            'project_info' => $projectInfo,
            'rows' => $rows,
            'raw_columns' => $rawColumns,
            'raw_rows' => $rawRows,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Preserve original cell positions for the Excel-like preview without
     * materialising every blank coordinate in a large worksheet.
     */
    private function extractRawPreview(Worksheet $sheet, int $highestLoadedRow): array
    {
        $rawRows = [];
        for ($rowNum = 1; $rowNum <= $highestLoadedRow; $rowNum++) {
            $rawRows[$rowNum] = ['row_number' => $rowNum, 'values' => []];
        }

        $usedColumnIndexes = [];
        foreach ($sheet->getRowIterator(1, $highestLoadedRow) as $row) {
            $rowNumber = $row->getRowIndex();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    $cachedValue = $cell->getOldCalculatedValue();
                    if ($cachedValue !== null) {
                        $value = $cachedValue;
                    }
                }
                if ($value === null || $value === '') {
                    continue;
                }

                $column = $cell->getColumn();
                $columnIndex = Coordinate::columnIndexFromString($column);
                $usedColumnIndexes[$columnIndex] = true;
                $rawRows[$rowNumber]['values'][$column] = is_scalar($value) ? $value : (string) $value;
            }
        }

        $usedColumnIndexes = array_keys($usedColumnIndexes);
        sort($usedColumnIndexes);
        $highestUsedColumn = $usedColumnIndexes ? max($usedColumnIndexes) : 1;

        // Preserve ordinary blank gaps exactly as Excel does. A very distant
        // stray cell (for example XEP) should not create sixteen thousand
        // empty preview columns, so extremely wide sheets show every column
        // that actually contains a value.
        if ($highestUsedColumn <= 256) {
            $columnIndexes = range(1, $highestUsedColumn);
        } else {
            $columnIndexes = $usedColumnIndexes ?: [1];
        }
        $rawColumns = array_map(
            fn (int $columnIndex) => Coordinate::stringFromColumnIndex($columnIndex),
            $columnIndexes
        );

        return [$rawColumns, array_values($rawRows)];
    }

    private function worksheetTotalRows(string $filePath, string $sheetName, int $fallback): int
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            foreach ($reader->listWorksheetInfo($filePath) as $info) {
                if (($info['worksheetName'] ?? null) === $sheetName) {
                    return (int) ($info['totalRows'] ?? $fallback);
                }
            }
        } catch (\Throwable $e) {
            // Metadata is informational; keep the loaded-row count if a
            // particular reader cannot provide worksheet info.
        }

        return $fallback;
    }

    /**
     * Load a bounded row range from one worksheet. PhpSpreadsheet otherwise
     * allocates cell objects for every row and sheet in the workbook.
     */
    private function loadSheetRange(string $filePath, string $sheetName, int $startRow, int $endRow): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $reader->setReadFilter(new class($startRow, $endRow) implements IReadFilter {
            public function __construct(private readonly int $startRow, private readonly int $endRow) {}

            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row <= $this->endRow;
            }
        });

        return $reader->load($filePath);
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
