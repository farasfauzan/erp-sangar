<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\RabBudget;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportGorontaloSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Starting Sekolah Rakyat Gorontalo import...");

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

        // 2. RAB already imported via ImportGorontaloRAB seeder

        // 3. Import POs from all PO files
        $this->importPOs($project);

        $this->command->info('Gorontalo import complete!');
    }

    private function importRab(Project $project)
    {
        $filePath = storage_path('app/excel/C.1 Rev. RAB SEKOLAH GORONTALO.xlsx');
        if (!file_exists($filePath)) {
            $this->command->error("RAB file not found");
            return;
        }

        $this->command->info("Loading RAB Excel...");
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('RAB');
        if (!$sheet) {
            $sheet = $spreadsheet->getSheetByName('REKAP');
        }
        if (!$sheet) {
            $this->command->error('RAB sheet not found! Available: ' . implode(', ', $spreadsheet->getSheetNames()));
            return;
        }

        $this->command->info("Parsing RAB sheet: " . $sheet->getTitle() . " (Rows: " . $sheet->getHighestRow() . ")");

        $items = [];
        $itemNo = 0;
        $foundData = false;

        // Iterate rows - data starts around row 10 based on analysis
        for ($i = 10; $i <= $sheet->getHighestRow(); $i++) {
            // Use getCell() which handles formulas
            $cVal = $sheet->getCell('C' . $i);
            $dVal = $sheet->getCell('D' . $i);
            $eVal = $sheet->getCell('E' . $i);
            $fVal = $sheet->getCell('F' . $i);
            $gVal = $sheet->getCell('G' . $i);

            $uraian = $cVal->getCalculatedValue();
            $satuan = $dVal->getCalculatedValue();
            $volume = $eVal->getCalculatedValue();
            $harga = $fVal->getCalculatedValue();
            $jumlah = $gVal->getCalculatedValue();

            if (!$uraian) continue;
            $uraianStr = trim((string)$uraian);

            // Skip section headers
            $bVal = $sheet->getCell('B' . $i);
            $kode = $bVal->getCalculatedValue();
            $kodeStr = trim((string)($kode ?? ''));
            if ($kodeStr && preg_match('/^[A-Z]\.$/', $kodeStr)) {
                continue;
            }

            $vol = is_numeric($volume) ? (float)$volume : 0;
            $hrg = is_numeric($harga) ? (float)$harga : 0;
            $jml = is_numeric($jumlah) ? (float)$jumlah : 0;

            // First data row is row 11 (Penyiapan Dokumen...)
            if ($vol === 0 && $hrg === 0 && $jml === 0) continue;

            $items[] = [
                'item_no' => ++$itemNo,
                'code' => $kode ? trim((string)$kode) : null,
                'description' => trim((string)$uraian),
                'unit' => trim((string)($satuan ?? 'unit')),
                'qty' => $vol,
                'price' => $hrg,
                'total' => $jml,
                'category' => 'Material',
            ];
        }

        $this->command->info("Parsed " . count($items) . " RAB items");

        // Import to DB in batches
        $batchSize = 100;
        $total = count($items);
        foreach (array_chunk($items, $batchSize) as $batchIndex => $batch) {
            $this->command->line("  Batch " . ($batchIndex + 1) . " (" . count($batch) . " items)...");
            foreach ($batch as $item) {
                if (empty($item['description'])) continue;

                RabBudget::create([
                    'project_id' => $project->id,
                    'code_item' => $item['code'] ?? 'ITEM-' . rand(1000, 9999),
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'volume' => $item['qty'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'category' => 'Material',
                    'status' => 'DRAFT',
                ]);
            }
            $this->command->line("  Done batch " . ($batchIndex + 1));
        }
        $this->command->info("Imported " . count($items) . " RAB items");
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
        $this->command->info("Loading RAB Excel...");
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
        }

        // Free spreadsheet memory
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