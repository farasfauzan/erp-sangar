<?php

namespace App\Imports;

use App\Models\RabBudget;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithStartRow;

class RabImport implements ToModel, WithChunkReading, WithBatchInserts, WithStartRow
{
    protected $projectId;
    protected $headerRow;
    protected $mapping;

    public function __construct($projectId, $headerRow, $mapping)
    {
        $this->projectId = $projectId;
        $this->headerRow = $headerRow;
        $this->mapping = $mapping;
    }

    public function startRow(): int
    {
        return (int) $this->headerRow + 1; // Start importing from the row right after the header
    }

    public function model(array $row)
    {
        // Extract using mapping (which contains column index numbers)
        // e.g. mapping['description'] = 2
        
        $descIndex = $this->mapping['description'] ?? null;
        if ($descIndex === null || empty(trim((string)($row[$descIndex] ?? '')))) {
            return null; // Description is required, skip if empty row
        }

        $codeIndex  = $this->mapping['code_item'] ?? null;
        $unitIndex  = $this->mapping['unit'] ?? null;
        $volIndex   = $this->mapping['volume'] ?? null;
        $priceIndex = $this->mapping['unit_price'] ?? null;
        $totalIndex = $this->mapping['total_price'] ?? null;
        $catIndex   = $this->mapping['category'] ?? null;

        $desc  = $row[$descIndex] ?? '';
        $code  = $codeIndex !== null ? ($row[$codeIndex] ?? null) : null;
        $unit  = $unitIndex !== null ? ($row[$unitIndex] ?? null) : null;
        $vol   = $volIndex !== null ? ($row[$volIndex] ?? null) : null;
        $price = $priceIndex !== null ? ($row[$priceIndex] ?? null) : null;
        $total = $totalIndex !== null ? ($row[$totalIndex] ?? null) : null;
        $cat   = $catIndex !== null ? ($row[$catIndex] ?? null) : null;

        return new RabBudget([
            'project_id'  => $this->projectId,
            'code_item'   => $code ? substr((string)$code, 0, 255) : '-',
            'description' => (string)$desc,
            'unit'        => $unit ? substr((string)$unit, 0, 50) : 'LS',
            'volume'      => $this->parseNumber($vol),
            'unit_price'  => $this->parseNumber($price),
            'total_price' => $this->parseNumber($total),
            'category'    => $cat ? substr((string)$cat, 0, 255) : 'Umum',
        ]);
    }

    private function parseNumber($value)
    {
        if (empty($value)) return 0;
        $val = preg_replace('/[^0-9.,\-]/', '', (string)$value);
        
        $hasComma = strpos($val, ',') !== false;
        $hasDot = strpos($val, '.') !== false;

        if ($hasComma && $hasDot) {
            if (strrpos($val, ',') > strrpos($val, '.')) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            } else {
                $val = str_replace(',', '', $val);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $val);
            if (strlen(end($parts)) === 3) {
                $val = str_replace(',', '.', $val); 
            } else {
                $val = str_replace(',', '.', $val);
            }
        }
        
        return (float) $val;
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function chunkSize(): int
    {
        return 200;
    }
}