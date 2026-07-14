<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for RSUD Mentawai format
 * Sheet: "rab"
 * Columns: No | Uraian Pekerjaan | Volume | Satuan | Harga Satuan | Jumlah Harga
 */
class MentawaiStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['rab'];
    }

    public function getHeaderRow(Worksheet $sheet): int
    {
        // Find row with "No" and "Uraian" keywords
        for ($row = 1; $row <= min(20, $sheet->getHighestRow()); $row++) {
            $cellA = $sheet->getCell('A' . $row)->getValue();
            $cellB = $sheet->getCell('B' . $row)->getValue();
            $cellC = $sheet->getCell('C' . $row)->getValue();
            $cellD = $sheet->getCell('D' . $row)->getValue();
            $cellE = $sheet->getCell('E' . $row)->getValue();
            $cellF = $sheet->getCell('F' . $row)->getValue();
            
            $vals = array_filter([$cellA, $cellB, $cellC, $cellD, $cellE, $cellF], fn($v) => $v !== null && $v !== '');
            $str = strtolower(implode(' ', array_map('strval', $vals)));
            
            if (strpos($str, 'no') !== false && strpos($str, 'uraian') !== false && strpos($str, 'volume') !== false) {
                return $row;
            }
        }
        return parent::findHeaderRow($sheet);
    }

    public function parseRow(array $rowData, int $rowNum): ?array
    {
        $normalized = $this->normalizeRowData($rowData, $this->getColumnMap($this->currentSheet));
        
        // Skip category headers
        if ($this->isCategoryHeader($normalized)) {
            $this->updateCategories($normalized);
            return null;
        }

        if (empty($normalized['description'])) return null;

        return [
            'code' => $normalized['code'] ?: 'ITEM-' . $rowNum,
            'description' => $normalized['description'],
            'unit' => $normalized['unit'] ?: 'unit',
            'qty' => $normalized['qty'],
            'price' => $normalized['price'],
            'total' => $normalized['total'],
            'category' => $this->currentCategory,
            'sub_category' => $this->currentSubCategory,
        ];
    }

    public function extractProjectInfo(Worksheet $sheet): array
    {
        $info = ['name' => '', 'location' => '', 'year' => ''];
        
        for ($row = 1; $row <= min(15, $sheet->getHighestRow()); $row++) {
            $cellA = $sheet->getCell('A' . $row)->getValue();
            $cellB = $sheet->getCell('B' . $row)->getValue();
            $cellC = $sheet->getCell('C' . $row)->getValue();
            
            $valA = $cellA ? trim((string)$cellA) : '';
            $valB = $cellB ? trim((string)$cellB) : '';
            $valC = $cellC ? trim((string)$cellC) : '';
            
            if (strpos($valA, 'Nama Paket') === 0 || strpos($valA, 'Nama Paket') !== false) {
                $info['name'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Lokasi') === 0 || strpos($valA, 'Lokasi') !== false) {
                $info['location'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Tahun') === 0) {
                $info['year'] = $valB;
            }
        }
        return $info;
    }
}