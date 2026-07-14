<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for WFC Barito format
 * Sheets: "PERSIAPAN", "SECTION 1", "SECTION 2", "SECTION 3"
 * Each section sheet has its own data structure
 * REKAP sheet references these sections
 */
class WfcBaritoStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['PERSIAPAN', 'SECTION 1', 'SECTION 2', 'SECTION 3'];
    }

    public function getColumnMap(Worksheet $sheet): array
    {
        $sheetName = $sheet->getTitle();
        
        // Each section sheet has different structure, scan for headers
        return $this->buildColumnMap($sheet, $this->findHeaderRow($sheet));
    }

    public function extractProjectInfo(Worksheet $sheet): array
    {
        // Get info from REKAP sheet if available
        $spreadsheet = $sheet->getParent();
        if ($spreadsheet && $spreadsheet->sheetNameExists('REKAP')) {
            $rekap = $spreadsheet->getSheetByName('REKAP');
            return $this->extractFromRekap($rekap);
        }
        
        return ['name' => '', 'location' => '', 'year' => ''];
    }

    private function extractFromRekap(Worksheet $rekap): array
    {
        $info = ['name' => '', 'location' => '', 'year' => ''];
        
        for ($row = 1; $row <= min(20, $rekap->getHighestRow()); $row++) {
            $cellA = $rekap->getCell('A' . $row)->getValue();
            $cellB = $rekap->getCell('B' . $row)->getValue();
            $cellC = $rekap->getCell('C' . $row)->getValue();
            
            $valA = $cellA ? trim((string)$cellA) : '';
            $valB = $cellB ? trim((string)$cellB) : '';
            $valC = $cellC ? trim((string)$cellC) : '';
            
            if (strpos($valA, 'PEKERJAAN') !== false && strpos($valA, ':') !== false) {
                $info['name'] = trim(str_replace(['PEKERJAAN', ':'], '', $valA . ' ' . $valB));
            } elseif (strpos($valA, 'LOKASI') !== false && strpos($valA, ':') !== false) {
                $info['location'] = trim(str_replace(['LOKASI', ':'], '', $valA . ' ' . $valB));
            } elseif (strpos($valA, 'TA') === 0) {
                $info['year'] = $valB;
            }
        }
        return $info;
    }

    public function parseRow(array $rowData, int $rowNum): ?array
    {
        $normalized = $this->normalizeRowData($rowData, $this->getColumnMap($this->currentSheet));
        
        if (empty($normalized['description'])) return null;
        
        // Skip summary rows
        $desc = strtoupper($normalized['description']);
        if (strpos($desc, 'JUMLAH') !== false || strpos($desc, 'TOTAL') !== false || strpos($desc, 'PPN') !== false) {
            return null;
        }

        // WFC sheets might not have numeric codes, check qty/price
        if ($normalized['qty'] == 0 && $normalized['price'] == 0) {
            // Might be a sub-header, check if it's a category
            if ($this->isCategoryHeader($normalized)) {
                $this->updateCategories($normalized);
            }
            return null;
        }

        return [
            'code' => $normalized['code'] ?: 'ITEM-' . $rowNum,
            'description' => $normalized['description'],
            'unit' => $normalized['unit'] ?: 'unit',
            'qty' => $normalized['qty'],
            'price' => $normalized['price'],
            'total' => $normalized['total'] ?: ($normalized['qty'] * $normalized['price']),
            'category' => $this->currentCategory,
            'sub_category' => $this->currentSubCategory,
        ];
    }
}