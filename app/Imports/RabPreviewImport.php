<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithLimit;

class RabPreviewImport implements ToCollection, WithLimit
{
    public $data = [];

    public function collection(Collection $rows)
    {
        // Convert rows to plain array, preserving string values
        foreach ($rows as $row) {
            $rowData = [];
            foreach ($row as $cell) {
                $rowData[] = (string) $cell;
            }
            $this->data[] = $rowData;
        }
    }

    public function limit(): int
    {
        return 30; // Limit to 30 rows for preview
    }
}
