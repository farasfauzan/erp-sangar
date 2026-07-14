<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for Sekolah Gorontalo format
 * Sheet: "RAB" and "RAB MEP"
 * Columns: NO. | URAIAN PEKERJAAN | SAT. | VOLUME | HARGA SATUAN (Rp.) | JUMLAH HARGA (Rp.)
 */
class SekolahGorontaloStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['RAB', 'RAB MEP'];
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
            
            if (strpos($valA, 'PEKERJAAN') === 0) {
                $info['name'] = $valB ?: $valC;
            } elseif (strpos($valA, 'LOKASI') === 0) {
                $info['location'] = $valB ?: $valC;
            } elseif (strpos($valA, 'TAHUN') === 0) {
                $info['year'] = $valB ?: $valC;
            }
        }
        return $info;
    }
}