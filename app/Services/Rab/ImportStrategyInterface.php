<?php

namespace App\Services\Rab;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Interface for RAB import strategies
 * Each strategy handles a specific Excel format
 */
interface ImportStrategyInterface
{
    /**
     * Return sheet names this strategy can handle
     * First match wins when auto-detecting
     */
    public function getTargetSheetNames(): array;

    /**
     * Build column mapping from header row
     * Returns: ['code' => colIndex, 'description' => colIndex, 'unit' => colIndex, 'qty' => colIndex, 'price' => colIndex, 'total' => colIndex]
     */
    public function getColumnMap(Worksheet $sheet): array;

    /**
     * Parse a single row of data
     * Return null to skip row, or array with keys: code, description, unit, qty, price, total, category, sub_category, row_number
     */
    public function parseRow(array $rowData, int $rowNum): ?array;

    /**
     * Extract project info from sheet (name, location, year, etc)
     */
    public function extractProjectInfo(Worksheet $sheet): array;

    /**
     * Check if this strategy matches the given sheet
     */
    public function matches(Worksheet $sheet): bool;
}