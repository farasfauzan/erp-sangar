<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('import:gik-ugm', function () {
    // ... existing gik-ugm command ...
    $this->info('GIK UGM Import Complete!');
    return 0;
})->purpose('Import GIK UGM data (RAB, Suppliers, POs)');

// Legacy inline importer kept only for reference. The maintained importer
// lives in App\Console\Commands\ImportGikRab.
Artisan::command('legacy:import:gik-rab', function () {
    $this->info('Starting GIK UGM RAB import...');
    
    // 1. Get Project
    $project = \App\Models\Project::where('project_name', 'GIK UGM')->first();
    if (!$project) {
        $this->error('Project GIK UGM not found!');
        return 1;
    }
    $this->info("Project: {$project->project_name} (ID: {$project->id})");
    
    // 2. Load Excel
    $filePath = storage_path('app/excel/C.1 RAB GIK UGM Ulang.xlsx');
    if (!file_exists($filePath)) {
        $this->error("File not found: $filePath");
        return 1;
    }
    
    $this->info("Loading Excel file...");
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('RAB GIK UGM');
    
    if (!$sheet) {
        $this->error('Sheet "RAB GIK UGM" not found!');
        return 1;
    }
    
    $this->info("Parsing sheet: " . $sheet->getTitle() . " (Rows: " . $sheet->getHighestRow() . ")");
    
    // 3. Parse all rows
    $items = [];
    $currentCategory = null;
    $currentSubCategory = null;
    $itemNo = 0;
    
    foreach ($sheet->getRowIterator(8) as $row) { // Start from row 8 (skip headers)
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
        if ($kode && in_array(trim((string)$kode), range('A', 'Z'))) {
            if (stripos($uraianStr, 'MATA PEMBAYARAN') !== false) {
                $currentCategory = $uraianStr;
            } elseif (preg_match('/PEKERJAAN|STRUKTUR|ARSITEKTUR|MEP|UTAMA|PERSIAPAN|SMKKK/i', $uraianStr)) {
                $currentSubCategory = $uraianStr;
            }
            continue;
        }
        
        // Skip headers
        if ($volume === null && $harga === null && $jumlah === null) continue;
        
        $vol = is_numeric($volume) ? (float)$volume : 0;
        $hrg = is_numeric($harga) ? (float)$harga : 0;
        $jml = is_numeric($jumlah) ? (float)$jumlah : 0;
        
        if ($vol === 0 && $hrg === 0 && $jml === 0) continue;
        
        $itemNo++;
        $items[] = [
            'item_no' => $itemNo,
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
    
    $this->info("Parsed " . count($items) . " items");
    
    // 3. Create categories
    $this->info("Creating categories...");
    $categories = [];
    foreach ($items as $item) {
        $catName = $item['category'] ?? 'Umum';
        if (!isset($categories[$catName])) {
            $cat = \App\Models\RabBudget::firstOrCreate(
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
            $this->line("  Category: {$catName} (ID: {$cat->id})");
        }
    }
    
    // 4. Import items
    $this->info("Importing " . count($items) . " RAB items...");
    $batchSize = 100;
    $total = count($items);
    
    foreach (array_chunk($items, 100) as $batchIndex => $batch) {
        $this->line("  Batch " . ($batchIndex + 1) . " (" . count($batch) . " items)...");
        
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
        
        $this->line("  Done batch " . ($batchIndex + 1));
    }
    
    $this->info('Import complete! Total: ' . count($items) . ' items');
    return 0;
})->purpose('Import full RAB GIK UGM from Excel file');
