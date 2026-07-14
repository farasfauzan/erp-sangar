<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for RS Muara Teweh format
 * Sheet: "RAB"
 * Columns: NO | Jenis Barang/Jasa | Satuan Unit | Volume | Harga Satuan | Jumlah Harga
 */
class MuaraTewehStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['RAB'];
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
            
            if (strpos($valA, 'Paket Pekerjaan') !== false) {
                $info['name'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Lokasi Pekerjaan') !== false) {
                $info['location'] = $valB ?: $valC;
            } elseif (strpos($valA, 'Tahun') === 0) {
                $info['year'] = $valB ?: $valC;
            }
        }
        return $info;
    }
}