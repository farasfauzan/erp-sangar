<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\RabBudget;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportGikRab extends Command
{
    protected $signature = 'import:gik-rab
                            {--dry-run : Parse and validate the workbook without changing RAB data}
                            {--replace : Replace the current GIK UGM RAB version after a successful parse}';
    protected $description = 'Import full RAB GIK UGM from Excel file';

    public function handle()
    {
        $this->info('Starting GIK UGM RAB import...');

        // This workbook is large and contains formulas that refer to other
        // sheets. Give PhpSpreadsheet enough headroom to evaluate them.
        ini_set('memory_limit', '512M');
        
        // 1. Get Project
        $project = Project::where('project_name', 'GIK UGM')->first();
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
        $spreadsheet = IOFactory::load($filePath);
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
        
        foreach ($sheet->getRowIterator(9) as $row) { // row 8 is the 1..6 column-number header
            $rowNumber = $row->getRowIndex();
            $cells = [];
            foreach (range('A', 'G') as $column) {
                $cells[$column] = $this->cellValue($sheet->getCell($column.$rowNumber));
            }

            // This RAB's layout is A=nomor/seksi, C=uraian, D=satuan,
            // E=volume, F=harga satuan, G=jumlah. Column B is intentionally blank.
            $sectionCode = trim($this->stringValue($cells['A'] ?? null));
            $uraianStr = trim($this->stringValue($cells['C'] ?? null));
            $satuan = $this->stringValue($cells['D'] ?? null);
            $volume = $cells['E'] ?? null;
            $harga = $cells['F'] ?? null;
            $jumlah = $cells['G'] ?? null;

            if ($uraianStr === '') continue;
            
            // Section headers
            if (preg_match('/^[A-Z]+\.?$/', $sectionCode)
                && stripos($uraianStr, 'MATA PEMBAYARAN') !== false) {
                $currentCategory = $uraianStr;
                $currentSubCategory = null;
                continue;
            }

            if ($volume === null && $harga === null && $jumlah === null
                && preg_match('/PEKERJAAN|STRUKTUR|ARSITEKTUR|MEP|UTAMA|PERSIAPAN|SMKKK/i', $uraianStr)) {
                $currentSubCategory = $uraianStr;
                continue;
            }
            
            // Skip headers
            if ($volume === null && $harga === null && $jumlah === null) continue;
            
            $vol = $this->numericValue($volume);
            $hrg = $this->numericValue($harga);
            $jml = $this->numericValue($jumlah);

            if ($jml === 0.0 && $vol > 0 && $hrg > 0) {
                $jml = $vol * $hrg;
            }
            
            if ($vol === 0 && $hrg === 0 && $jml === 0) continue;
            
            $items[] = [
                'item_no' => ++$itemNo,
                'code' => null,
                'description' => $uraianStr,
                'unit' => trim($satuan) ?: 'unit',
                'qty' => $vol,
                'price' => $hrg,
                'total' => $jml,
                'category' => $currentCategory ?? 'Umum',
                'sub_category' => $currentSubCategory ?? null,
            ];
        }
        
        $this->info("Parsed " . count($items) . " items");
        $this->table(
            ['Uraian', 'Volume', 'Harga Satuan', 'Jumlah'],
            collect($items)->take(5)->map(fn (array $item) => [
                $item['description'],
                number_format($item['qty'], 2, ',', '.'),
                number_format($item['price'], 2, ',', '.'),
                number_format($item['total'], 2, ',', '.'),
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run selesai. Tidak ada data RAB yang diubah.');
            return self::SUCCESS;
        }

        if (! $this->option('replace')) {
            $this->warn('Tidak ada data yang ditulis. Jalankan kembali dengan --replace setelah memeriksa hasil dry run.');
            return self::SUCCESS;
        }

        $existingCount = RabBudget::where('project_id', $project->id)->count();
        if ($existingCount > 0) {
            $this->warn("Mengarsipkan {$existingCount} data RAB GIK UGM lama sebelum impor ulang.");
            RabBudget::where('project_id', $project->id)->delete();
        }
        
        // 3. Create categories
        $this->info("Creating categories...");
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
                
                RabBudget::create([
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
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return $value->getPlainText();
        }

        return trim((string) $value);
    }

    private function numericValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $number = preg_replace('/[^0-9,.-]/', '', $this->stringValue($value));
        if ($number === '' || $number === '-' || $number === null) {
            return 0.0;
        }

        $lastComma = strrpos($number, ',');
        $lastDot = strrpos($number, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $number = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $number))
                : str_replace(',', '', $number);
        } elseif ($lastComma !== false) {
            $number = str_replace(',', '.', $number);
        }

        return is_numeric($number) ? (float) $number : 0.0;
    }

    private function cellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        $value = $cell->getValue();
        if (! is_string($value) || ! str_starts_with($value, '=')) {
            return $value;
        }

        // Excel stored the calculated formula result in this workbook. Reading
        // it directly avoids recalculating every cross-sheet formula in PHP.
        $cachedValue = $cell->getOldCalculatedValue();
        return $cachedValue ?? $cell->getCalculatedValue();
    }
}
