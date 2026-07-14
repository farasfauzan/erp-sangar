<?php

namespace App\Services\Rab\Strategies;

use App\Services\Rab\BaseImportStrategy;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Strategy for GIK UGM format (and similar)
 * Sheet: "RAB GIK UGM", "RAB", "RAB MEP"
 * Columns: Nomor | Jenis Barang/Jasa | Satuan Unit | Volume | Harga Satuan (Rp) | Jumlah Harga Satuan (Rp)
 */
class GikUgmStrategy extends BaseImportStrategy
{
    public function getTargetSheetNames(): array
    {
        return ['RAB GIK UGM', 'RAB', 'RAB MEP'];
    }
}