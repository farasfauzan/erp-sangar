<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for SPAM Jingah format
 * Sheet: "RAB"
 * Columns: No. | URAIAN PEKERJAAN | SAT. | KODE ANALISA | QTY | JUMLAH
 * References HSP sheet for prices
 */
class SpamJingahStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['RAB'];
    }

    public function getColumnMap(Worksheet $sheet): array
    {
        // Fixed column mapping for SPAM Jingah RAB sheet
        // Row 6 has headers: A=No., B=URAIAN PEKERJAAN, D=SAT., E=KODE ANALISA, F=QTY, G=HARGA, H=JUMLAH
        return [
            'A' => 'code',
            'B' => 'description',
            'D' => 'unit',
            'F' => 'qty',
            'G' => 'price',
            'H' => 'total',
        ];
    }

    public function findHeaderRow(Worksheet $sheet): int
    {
        // SPAM Jingah RAB sheet has headers at row 6
        // Row 6: A=No., B=URAIAN PEKERJAAN, D=SAT., E=KODE ANALISA, F=QTY, H=JUMLAH
        for ($row = 1; $row <= min(20, $sheet->getHighestRow()); $row++) {
            $valA = $sheet->getCell('A' . $row)->getValue();
            $valB = $sheet->getCell('B' . $row)->getValue();
            $valD = $sheet->getCell('D' . $row)->getValue();
            $valF = $sheet->getCell('F' . $row)->getValue();
            
            if (strpos(strtolower($valA ?? ''), 'no') !== false &&
                strpos(strtolower($valB ?? ''), 'uraian') !== false &&
                strpos(strtolower($valD ?? ''), 'sat') !== false &&
                strpos(strtolower($valF ?? ''), 'qty') !== false) {
                return $row;
            }
        }
        return 6; // Default for SPAM Jingah
    }

    public function parseRow(array $rowData, int $rowNum): ?array
    {
        $code = trim((string)($rowData['A'] ?? ''));
        $description = trim((string)($rowData['B'] ?? ''));
        $unit = trim((string)($rowData['D'] ?? ''));
        $qty = $this->parseNumber($rowData['F'] ?? 0);
        $price = $this->parseNumber($rowData['G'] ?? 0);
        $total = $this->parseNumber($rowData['H'] ?? 0);

        // Skip empty rows
        if (empty($description) && $qty == 0 && $price == 0) {
            return null;
        }

        // Skip category headers (I, II, III, A., B., etc. with no qty/price)
        if (empty($description) || ($qty == 0 && $price == 0)) {
            // Check if it's a category header
            $upperDesc = strtoupper($description);
            if (preg_match('/^[IVX]+$/', $code) || 
                preg_match('/^[A-Z]\.$/', $code) ||
                strpos($upperDesc, 'PEKERJAAN') !== false ||
                strpos($upperDesc, 'SISTEM MANAJEMEN') !== false) {
                // Update category tracking
                if (preg_match('/^[A-Z]\.$/', $code) && strpos($upperDesc, 'MATA PEMBAYARAN') !== false) {
                    $this->currentCategory = $description;
                    $this->currentSubCategory = '';
                } elseif (strpos($upperDesc, 'PEKERJAAN') !== false || strpos($upperDesc, 'SMK') !== false) {
                    $this->currentSubCategory = $description;
                }
            }
            return null;
        }

        // Calculate total if missing (formula reference)
        if ($total == 0 && $qty > 0 && $price > 0) {
            $total = $qty * $price;
        }

        return [
            'code' => $code ?: 'ITEM-' . $rowNum,
            'description' => $description,
            'unit' => $unit ?: 'unit',
            'qty' => $qty,
            'price' => $price,
            'total' => $total,
            'category' => $this->currentCategory,
            'sub_category' => $this->currentSubCategory,
            'row_number' => $rowNum,
        ];
    }

    public function extractProjectInfo(Worksheet $sheet): array
    {
        $info = ['name' => '', 'location' => '', 'year' => ''];

        for ($row = 1; $row <= min(10, $sheet->getHighestRow()); $row++) {
            $cellA = $sheet->getCell('A' . $row)->getValue();
            $cellB = $sheet->getCell('B' . $row)->getValue();
            $cellC = $sheet->getCell('C' . $row)->getValue();

            $valA = $cellA ? trim((string)$cellA) : '';
            $valB = $cellB ? trim((string)$cellB) : '';
            $valC = $cellC ? trim((string)$cellC) : '';

            if (strpos($valA, 'Pekerjaan') === 0) {
                $info['name'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Lokasi') === 0) {
                $info['location'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Tahun') === 0) {
                $info['year'] = $valB ?: $valC;
            }
        }
        return $info;
    }
}