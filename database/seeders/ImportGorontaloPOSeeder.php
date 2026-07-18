<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportGorontaloPOSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Starting Sekolah Rakyat Gorontalo PO import...");

        // 1. Get/Create Project
        $project = Project::firstOrCreate(
            ['project_name' => 'Sekolah Rakyat Gorontalo'],
            [
                'location' => 'Dekat Tugu KTM, Sejahtera, Kec. Wonosari Kab. Boalemo Gorontalo',
                'start_date' => '2025-01-01',
                'status' => 'planning',
            ]
        );
        $this->command->info("Project: {$project->project_name} (ID: {$project->id})");

        // 2. Import POs from all PO files
        $this->importPOs($project);

        $this->command->info('Gorontalo PO import complete!');
    }

    private function importPOs(Project $project)
    {
        $poFiles = [
            storage_path('app/excel/Purchase Order Sekolah Rakyat Gorontalo 1.xlsx'),
            storage_path('app/excel/Purchase Order Sekolah Rakyat Gorontalo 1-2.xlsx'),
            storage_path('app/excel/SEKOLAH RAKYAT GORONTALO-2.xlsx'),
            storage_path('app/excel/SR GORONTALO 2-2.xlsx'),
        ];

        foreach ($poFiles as $filePath) {
            if (!file_exists($filePath)) {
                $this->command->warn("File not found: $filePath");
                continue;
            }
            $this->importPoFile($project, $filePath);
        }
    }

    private function importPoFile(Project $project, $filePath)
    {
        $this->command->info("Processing PO file: " . basename($filePath));
        $spreadsheet = IOFactory::load($filePath);

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (strtolower($sheetName) === 'sheet1') continue; // Skip summary sheet
            
            // Skip non-vendor sheets
            $skipSheets = ['JADWAL DO HT', 'JADWAL DO HT GRANIT', 'JADWAL DO HT GRANIT VALENTINO'];
            $shouldSkip = false;
            foreach ($skipSheets as $skip) {
                if (stripos($sheetName, $skip) !== false) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) continue;

            $sheet = $spreadsheet->getSheetByName($sheetName);
            $this->parsePoSheet($project, $sheetName, $sheet);
            
            unset($sheet);
        }

        unset($spreadsheet);
        gc_collect_cycles();
    }

    private function parsePoSheet(Project $project, $sheetName, $sheet)
    {
        $vendor = null;
        $poNumber = null;
        $poDate = null;
        $location = '';
        $contact = '';

        // Parse header (first 20 rows)
        $rowCount = 0;
        foreach ($sheet->getRowIterator(1, 20) as $row) {
            $rowCount++;
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }
            $rowStr = implode(' ', array_filter($cells, fn($v) => $v !== null));

            // Vendor name (single cell with vendor-like name)
            if (!$vendor && count(array_filter($cells)) === 1) {
                $val = trim((string)($cells[0] ?? ''));
                if (preg_match('/(PT\.|CV\.|TOKO|UD |BENGKEL|SHOPEE|HT |SUMBER |CAHAYA |SUMBER |VARIA |MAHKOTA |HOME |BAMBU |BERKAH |SUMBER |MITRA |AGUNG |NURANI |DUNIA |CAHAYA )/i', $val)) {
                    $vendor = $val;
                }
            }

            // PO Number
            if (!$poNumber && strpos($rowStr, 'Nomor') !== false && strpos($rowStr, ':') !== false) {
                foreach ($cells as $cell) {
                    if ($cell && (strpos($cell, 'SCS-SMG') !== false || strpos($cell, 'PO/') !== false)) {
                        $poNumber = trim((string)$cell);
                        break;
                    }
                }
            }

            // Date
            foreach ($cells as $cell) {
                if ($cell instanceof \DateTime) {
                    $poDate = $cell->format('Y-m-d');
                } elseif ($cell && preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/', $cell)) {
                    $poDate = date('Y-m-d', strtotime(str_replace('/', '-', $cell)));
                }
            }

            // Location
            if (strpos($rowStr, 'Lokasi') !== false && strpos($rowStr, ':') !== false) {
                foreach ($cells as $cell) {
                    if ($cell && stripos($cell, 'Gorontalo') !== false) {
                        $location = trim((string)$cell);
                    }
                }
            }

            // Contact
            if (strpos($rowStr, 'Contact') !== false && strpos($rowStr, ':') !== false) {
                foreach ($cells as $cell) {
                    if ($cell && (stripos($cell, 'Bima') !== false || stripos($cell, 'Dedi') !== false || stripos($cell, 'Deni') !== false)) {
                        $contact = trim((string)$cell);
                    }
                }
            }
        }

        if (!$vendor || !$poNumber) {
            return;
        }

        // Create/Get Supplier
        $supplier = Supplier::firstOrCreate(
            ['name' => $vendor],
            [
                'code' => 'SUP-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $vendor), 0, 20)),
                'address' => $location,
                'phone' => $contact,
                'email' => '',
                'contact_person' => $contact,
            ]
        );

        // Create PO - use supplier_name instead of supplier_id
        $existingPo = PurchaseOrder::where('po_number', $poNumber)->first();
        if (!$existingPo) {
            PurchaseOrder::create([
                'project_id' => $project->id,
                'supplier_name' => $vendor,
                'po_number' => $poNumber,
                'date' => $poDate ?? '2026-01-01',
                'status' => 'draft',
                'po_type' => 'supplier',
                'payment_terms' => '30 hari',
                'contact_person' => $contact,
                'supplier_address' => $location,
            ]);
            $this->command->line("  Created PO: $poNumber for $vendor");
        } else {
            $this->command->line("  PO exists: $poNumber");
        }
    }
}