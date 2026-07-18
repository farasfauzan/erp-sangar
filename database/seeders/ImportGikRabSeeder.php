<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\RabBudget;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportGikRabSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Starting GIK UGM RAB import...");

        // 1. Get Project
        $project = Project::where('project_name', 'GIK UGM')->first();
        if (!$project) {
            $this->command->error('Project GIK UGM not found!');
            return;
        }
        $this->command->info("Project: {$project->project_name} (ID: {$project->id})");

        // 2. Load Excel
        $filePath = storage_path('app/excel/C.1 RAB GIK UGM Ulang.xlsx');
        if (!file_exists($filePath)) {
            $this->command->error("File not found");
            return;
        }

        $this->command->info("Loading Excel file...");
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('RAB GIK UGM');

        if (!$sheet) {
            $this->command->error('Sheet "RAB GIK UGM" not found!');
            return;
        }

        $this->command->info("Parsing sheet: " . $sheet->getTitle() . " (Rows: " . $sheet->getHighestRow() . ")");

        // 3. Parse all rows
        $items = [];
        $currentCategory = null;
        $currentSubCategory = null;
        $itemNo = 0;

        foreach ($sheet->getRowIterator(8) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }

            // Columns: 0=No, 1=Kode, 2=Uraian, 3=Satuan, 4=Volume, 5=Harga, 6=Jumlah
            $no = $cells[0] ?? null;
            $kode = $cells[1] ?? null;
            $uraian = $cells[2] ?? null;
            $satuan = $cells[3] ?? null;
            $volume = $cells[4] ?? null;
            $harga = $cells[5] ?? null;
            $jumlah = $cells[6] ?? null;

            if (!$uraian) continue;
            $uraianStr = trim((string)$uraian);

            // Detect section headers
            // Section headers have codes like "A.", "B.", "C." in column 0 (No)
            if ($kode && preg_match('/^[A-Z]\.$/', trim((string)$kode))) {
                if (stripos($uraianStr, 'MATA PEMBAYARAN') !== false) {
                    $currentCategory = $uraianStr;
                } elseif (preg_match('/PEKERJAAN|STRUKTUR|ARSITEKTUR|MEP|UTAMA|PERSIAPAN|SMKKK/i', $uraianStr)) {
                    $currentSubCategory = $uraianStr;
                    // Also set as category if no main category yet
                    if (!$currentCategory) {
                        $currentCategory = $uraianStr;
                    }
                }
                continue;
            }

            // Skip headers
            if ($volume === null && $harga === null && $jumlah === null) continue;

            $vol = is_numeric($volume) ? (float)$volume : 0;
            $hrg = is_numeric($harga) ? (float)$harga : 0;
            $jml = is_numeric($jumlah) ? (float)$jumlah : 0;

            if ($vol === 0 && $hrg === 0 && $jml === 0) continue;

            $items[] = [
                'item_no' => ++$itemNo,
                'code' => $kode ? trim((string)$kode) : null,
                'description' => $uraianStr,
                'unit' => $satuan ? trim((string)$satuan) : 'unit',
                'qty' => $vol,
                'price' => $hrg,
                'total' => $jml,
                'category' => $currentCategory ?? 'Umum',
                'sub_category' => $currentSubCategory ?? null,
            ];
        }

        $this->command->info("Parsed " . count($items) . " items");

        // 3. Create categories
        $this->command->info("Creating categories...");
        $categories = [];
        foreach ($items as $item) {
            $catName = $item['category'] ?? 'Umum';
            if (!isset($categories[$catName])) {
                $cat = RabBudget::firstOrCreate(
                    ['description' => $catName, 'project_id' => $project->id, 'parent_id' => null],
                    [
                        'project_id' => $project->id,
                        'code_item' => 'CAT-' . strtoupper(substr($catName, 0, 3)),
                        'description' => $catName,
                        'unit' => '',
                        'volume' => 0,
                        'unit_price' => 0,
                        'total_price' => 0,
                        'category' => $catName,
                        'status' => 'APPROVED',
                    ]
                );
                $categories[$catName] = $cat->id;
                $this->command->line("  Category: {$catName} (ID: {$cat->id})");
            }
        }

        // 4. Import items in batches
        $this->command->info("Importing " . count($items) . " RAB items...");
        $batchSize = 100;
        $total = count($items);

        foreach (array_chunk($items, 100) as $batchIndex => $batch) {
            $this->command->line("  Batch " . ($batchIndex + 1) . " (" . count($batch) . " items)...");

            foreach ($batch as $item) {
                if (empty($item['description'])) continue;

                $catName = $item['category'] ?? 'Umum';
                $parentId = $categories[$catName] ?? null;

                \App\Models\RabBudget::create([
                    'project_id' => $project->id,
                    'parent_id' => $parentId,
                    'code_item' => $item['code'] ?? 'ITEM-' . $item['item_no'],
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'volume' => $item['qty'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'category' => $item['category'],
                    'status' => 'DRAFT',
                ]);
            }

            $this->command->line("  Done batch " . ($batchIndex + 1));
        }

        $this->command->info('Import complete! Total: ' . count($items) . ' items');
        return;
    }
}