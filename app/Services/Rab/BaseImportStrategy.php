<?php

namespace App\Services\Rab;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base strategy with common parsing logic
 */
abstract class BaseImportStrategy implements ImportStrategyInterface
{
    protected string $currentCategory = '';
    protected string $currentSubCategory = '';
    protected ?Worksheet $currentSheet = null;

    public function getColumnMap(Worksheet $sheet): array
    {
        $headerRow = $this->findHeaderRow($sheet);
        return $this->buildColumnMap($sheet, $headerRow);
    }

    public function parseRow(array $rowData, int $rowNum): ?array
    {
        $columnMap = $this->getColumnMap($this->currentSheet);
        if (empty($columnMap)) return null;

        $normalized = $this->normalizeRowData($rowData, $columnMap);

        if (empty($normalized['description']) && $normalized['qty'] == 0 && $normalized['price'] == 0) {
            return null; // Empty row
        }

        if ($this->isCategoryHeader($normalized)) {
            $this->updateCategories($normalized);
            return null; // Skip category rows
        }

        // Must have a numeric code or be a data row with qty/price
        $code = $normalized['code'];
        $isDataRow = is_numeric($code) || (is_string($code) && preg_match('/^\d+$/', $code));
        
        if (!$isDataRow && $normalized['qty'] == 0 && $normalized['price'] == 0) {
            return null; // Not a data row
        }

        return array_merge($normalized, [
            'category' => $this->currentCategory,
            'sub_category' => $this->currentSubCategory,
            'row_number' => $rowNum,
        ]);
    }

    public function extractProjectInfo(Worksheet $sheet): array
    {
        $info = ['name' => '', 'location' => '', 'year' => '', 'source' => ''];
        
        for ($row = 1; $row <= min(15, $sheet->getHighestRow()); $row++) {
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                $cellIterator = $r->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $val = trim((string)$cell->getValue());
                    if (empty($val)) continue;
                    
                    $lower = strtolower($val);
                    if (strpos($lower, 'pekerjaan') === 0 || strpos($lower, 'paket pekerjaan') === 0) {
                        $info['name'] = preg_replace('/^.*?:\s*/', '', $val);
                    } elseif (strpos($lower, 'lokasi') === 0) {
                        $info['location'] = preg_replace('/^.*?:\s*/', '', $val);
                    } elseif (strpos($lower, 'tahun') === 0 || strpos($lower, 'tahun anggaran') === 0) {
                        $info['year'] = preg_replace('/^.*?:\s*/', '', $val);
                    } elseif (strpos($lower, 'sumber dana') === 0) {
                        $info['source'] = preg_replace('/^.*?:\s*/', '', $val);
                    }
                }
            }
        }
        return $info;
    }

    public function matches(Worksheet $sheet): bool
    {
        foreach ($this->getTargetSheetNames() as $targetName) {
            foreach ($sheet->getParent()->getSheetNames() as $sheetName) {
                if (stripos($sheetName, $targetName) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    // Abstract method - each strategy defines its target sheets
    abstract public function getTargetSheetNames(): array;

    // ===== Helper methods =====

    public function findHeaderRow(Worksheet $sheet): int
    {
        $keywords = ['nomor', 'no.', 'uraian', 'jenis', 'satuan', 'volume', 'harga', 'jumlah', 'kode', 'item', 'deskripsi', 'qty', 'price', 'total'];
        
        for ($row = 1; $row <= min(20, $sheet->getHighestRow()); $row++) {
            $rowValues = [];
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                $cellIterator = $r->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $val = $cell->getValue();
                    if ($val !== null && $val !== '') {
                        $rowValues[] = strtolower(trim((string)$val));
                    }
                }
            }
            $rowStr = implode(' ', $rowValues);
            if (empty($rowStr)) continue;
            
            foreach ($keywords as $kw) {
                if (strpos($rowStr, $kw) !== false) {
                    return $row;
                }
            }
        }
        return 1;
    }

    protected function buildColumnMap(Worksheet $sheet, int $headerRow): array
    {
        $map = [];
        $keywords = [
            'code' => ['nomor', 'no.', 'no', 'kode', 'kode item', 'code', 'item'],
            'description' => ['uraian', 'uraian pekerjaan', 'jenis barang/jasa', 'deskripsi', 'nama barang', 'description', 'pekerjaan'],
            'unit' => ['satuan', 'satuan unit', 'unit'],
            'qty' => ['volume', 'qty', 'jumlah', 'kuantitas'],
            'price' => ['harga satuan', 'harga satuan (rp)', 'harga', 'harga_satuan', 'price'],
            'total' => ['jumlah harga', 'jumlah harga satuan', 'total', 'total (rp.)', 'jumlah'],
        ];

        $headerRowData = [];
        foreach ($sheet->getRowIterator($headerRow, $headerRow) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $col = $cell->getColumn();
                $val = strtolower(trim((string)$cell->getValue()));
                $headerRowData[$col] = $val;
            }
        }

        foreach ($headerRowData as $col => $header) {
            foreach ($keywords as $field => $kws) {
                foreach ($kws as $kw) {
                    if (strpos($header, $kw) !== false) {
                        $map[$col] = $field;
                        break 2;
                    }
                }
            }
        }

        // Fallback: guess by position
        if (count($map) < 4) {
            $cols = array_keys($headerRowData);
            $fallback = ['code', 'description', 'unit', 'qty', 'price', 'total'];
            foreach ($cols as $i => $col) {
                if (!isset($map[$col]) && isset($fallback[$i])) {
                    $map[$col] = $fallback[$i];
                }
            }
        }

        return $map;
    }

    protected function normalizeRowData(array $rowData, array $columnMap): array
    {
        $normalized = [
            'code' => '',
            'description' => '',
            'unit' => '',
            'qty' => 0,
            'price' => 0,
            'total' => 0,
        ];

        foreach ($columnMap as $col => $field) {
            $value = $rowData[$col] ?? null;
            if ($value === null || $value === '') continue;

            switch ($field) {
                case 'code':
                    $normalized['code'] = trim((string)$value);
                    break;
                case 'description':
                    $normalized['description'] = trim((string)$value);
                    break;
                case 'unit':
                    $normalized['unit'] = trim((string)$value);
                    break;
                case 'qty':
                    $normalized['qty'] = $this->parseNumber($value);
                    break;
                case 'price':
                    $normalized['price'] = $this->parseNumber($value);
                    break;
                case 'total':
                    $normalized['total'] = $this->parseNumber($value);
                    break;
            }
        }

        // Calculate total if missing
        if (($normalized['total'] == 0 || $normalized['total'] == '') && $normalized['qty'] && $normalized['price']) {
            $normalized['total'] = $normalized['qty'] * $normalized['price'];
        }

        return $normalized;
    }

    protected function parseNumber($value): float
    {
        if (is_numeric($value)) return (float)$value;
        $str = trim((string)$value);
        $str = str_replace(['.', ',', 'Rp', ' ', 'IDR'], '', $str);
        $str = preg_replace('/[^0-9\-\.]/', '', $str);
        return $str !== '' ? (float)$str : 0;
    }

    protected function isCategoryHeader(array $normalized): bool
    {
        $hasDesc = !empty($normalized['description']);
        $hasQty = $normalized['qty'] > 0;
        $hasPrice = $normalized['price'] > 0;
        $hasTotal = $normalized['total'] > 0;
        $code = strtoupper(trim($normalized['code']));

        if ($hasDesc && !$hasQty && !$hasPrice && !$hasTotal) {
            // Single letter or roman numeral = category
            if (preg_match('/^[A-Z]$/', $code) || preg_match('/^[IVX]+$/', $code)) {
                return true;
            }
            // Or contains keywords
            $desc = strtoupper($normalized['description']);
            if (preg_match('/MATA PEMBAYARAN|PEKERJAAN|STRUKTUR|ARSITEKTUR|MEP|UTAMA|PERSIAPAN|SMKKK|SMK3|SISTEM MANAJEMEN/', $desc)) {
                return true;
            }
        }
        return false;
    }

    protected function updateCategories(array $normalized): void
    {
        $desc = strtoupper($normalized['description']);
        $code = strtoupper(trim($normalized['code']));

        if (preg_match('/^[A-Z]$/', $code)) {
            if (strpos($desc, 'MATA PEMBAYARAN') !== false) {
                $this->currentCategory = $normalized['description'];
                $this->currentSubCategory = '';
            } elseif (preg_match('/PEKERJAAN|STRUKTUR|ARSITEKTUR|MEP|UTAMA|PERSIAPAN|SMKKK|SMK3/', $desc)) {
                $this->currentSubCategory = $normalized['description'];
            }
        }
    }

    public function setCurrentSheet(?Worksheet $sheet): void
    {
        $this->currentSheet = $sheet;
        $this->currentCategory = '';
        $this->currentSubCategory = '';
    }
}