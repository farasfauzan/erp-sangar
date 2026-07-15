<?php

namespace App\Jobs;

use App\Models\RabBudget;
use App\Models\RabImportJob;
use App\Traits\HandlesRabParsing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ValidateRabImportJob implements ShouldQueue
{
    use Dispatchable, HandlesRabParsing, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $job = RabImportJob::find($this->jobId);
        if (! $job) {
            return;
        }

        $job->update(['status' => RabImportJob::STATUS_PROCESSING]);

        try {
            // 1. Initial parse to identify all valid sheets and columns
            $rawResult = $this->parseRaw($job->file_path, $job->file_type, 100, $job->sheet_name);
            $sheets = $rawResult['sheets'];

            $validSheets = $this->findValidSheets($sheets);
            $multiSheetImport = count($validSheets) > 1;
            if (empty($validSheets)) {
                $job->update([
                    'status' => RabImportJob::STATUS_FAILED,
                    'errors' => ['Header tidak ditemukan dalam file Excel. Pastikan baris header mengandung kata kunci seperti: uraian, deskripsi, volume, harga, satuan, jumlah, dll.'],
                ]);

                return;
            }

            // 2. Perform streaming pass validation and collection across all valid sheets
            $errors = [];
            $newItems = [];
            $rowCount = 0;

            foreach ($validSheets as $sheetInfo) {
                if ($colError = $this->columnMapError($sheetInfo['colMap'])) {
                    continue; // Skip sheets without required columns
                }

                // Bug #2 fix: Reset code counters per sheet to avoid state leaking
                $this->resetCodeCounters();

                Log::info('Import using sheet: '.$sheetInfo['sheetName'].' colMap: '.json_encode($sheetInfo['colMap']));
                $currentCategory = $sheetInfo['sheetName']; // default category = sheet name
                $currentSectionCode = '0101';
                $currentResourceCategory = null;

                $mainSectionCount = 0;
                $subSectionCount = 1;
                $itemCount = 1;

                $resourceCounters = [
                    'Material' => 10001,
                    'Upah' => 10001,
                    'Alat' => 10001,
                    'Subkon' => 10001,
                ];

                foreach ($this->streamRows($job->file_path, $job->file_type, $sheetInfo['sheetName']) as $idx => $row) {
                    if ($idx <= $sheetInfo['headerIndex']) {
                        continue;
                    }

                    // Extract columns
                    $descCol = $sheetInfo['colMap']['uraian'] ?? -1;
                    $volCol = $sheetInfo['colMap']['volume'] ?? -1;
                    $priceCol = $sheetInfo['colMap']['harga_satuan'] ?? -1;
                    $kodeCol = $sheetInfo['colMap']['kode'] ?? -1;
                    $desc = trim((string) ($row[$descCol] ?? ''));
                    $vol = $row[$volCol] ?? null;
                    $price = $row[$priceCol] ?? null;
                    $amount = $row[$sheetInfo['colMap']['jumlah'] ?? -1] ?? null;
                    $kode = trim((string) ($row[$kodeCol] ?? ''));

                    // A hierarchical recap can carry a subtotal on its Roman-
                    // numeral section row. It is a category boundary, not an
                    // importable item, even though the amount cell is filled.
                    if ($this->isNumberedRecapSectionRow($row, $sheetInfo['colMap'], $desc)) {
                        $currentResourceCategory = null;
                        $mainSectionCount++;
                        $subSectionCount = 1;
                        $itemCount = 1;
                        $currentSectionCode = str_pad($mainSectionCount, 2, '0', STR_PAD_LEFT).'01';
                        $currentCategory = $multiSheetImport
                            ? $sheetInfo['sheetName'].' / '.$desc
                            : $desc;

                        continue;
                    }

                    // Only numbered parent rows are imported from hierarchical
                    // recaps. Resource subheadings between parent rows must not
                    // leak Material/Upah/Alat into the next parent category.
                    if ($this->isNumberedRecapItemRow($row, $sheetInfo['colMap'])) {
                        $currentResourceCategory = null;
                    }

                    // Detect section header: description present, all numeric columns empty/null
                    // Bug #4 fix: Use isEmptyCell() to handle both null and '' from XML streaming
                    if ($desc !== '' && $this->isEmptyCell($vol) && $this->isEmptyCell($price) && $this->isEmptyCell($amount)) {
                        $resCat = $this->detectResourceCategory($desc);
                        if ($resCat !== null) {
                            $currentResourceCategory = $resCat;
                        } else {
                            $currentResourceCategory = null;
                            if ($this->isLevel1Section($desc, $kode)) {
                                $mainSectionCount++;
                                $subSectionCount = 1;
                                $itemCount = 1;
                            } else {
                                $subSectionCount++;
                                $itemCount = 1;
                            }
                            $currentSectionCode = str_pad($mainSectionCount ?: 1, 2, '0', STR_PAD_LEFT).str_pad($subSectionCount, 2, '0', STR_PAD_LEFT);
                            $currentCategory = $multiSheetImport
                                ? $sheetInfo['sheetName'].' / '.$desc
                                : $desc;
                        }

                        continue;
                    }

                    $normalized = $this->normalizeRabRow($row, $sheetInfo['colMap'], $idx + 1, $currentSectionCode);

                    if (is_array($normalized) && isset($normalized['error'])) {
                        $errors[] = "Sheet '{$sheetInfo['sheetName']}' {$normalized['error']}";
                        if (count($errors) >= 100) {
                            $errors[] = 'Ditemukan lebih dari 100 kesalahan. Membatasi tampilan error.';
                            break 2;
                        }

                        continue;
                    }

                    if (! $normalized) {
                        continue;
                    }

                    // Generate hierarchical or resource code if empty
                    if (! isset($normalized['code_item']) || $normalized['code_item'] === null || $normalized['code_item'] === '') {
                        if ($currentResourceCategory !== null) {
                            $prefix = [
                                'Material' => 'M',
                                'Upah' => 'T',
                                'Alat' => 'A',
                                'Subkon' => 'S',
                            ][$currentResourceCategory] ?? 'M';
                            $normalized['code_item'] = $prefix.$resourceCounters[$currentResourceCategory]++;
                            $normalized['category'] = $multiSheetImport
                                ? $sheetInfo['sheetName'].' / '.$currentResourceCategory
                                : $currentResourceCategory;
                        } else {
                            $normalized['code_item'] = $currentSectionCode.str_pad($itemCount++, 2, '0', STR_PAD_LEFT);
                            $normalized['category'] = $currentCategory;
                        }
                    } else {
                        // Keep existing code, but assign appropriate category
                        if ($currentResourceCategory !== null) {
                            $normalized['category'] = $currentResourceCategory;
                        } else {
                            $normalized['category'] = $currentCategory;
                        }
                    }

                    $newItems[] = $normalized;
                    $rowCount++;
                }
                // Bug #1 fix: Do NOT break — process ALL valid sheets
            }

            if ($errors !== []) {
                $job->update([
                    'status' => RabImportJob::STATUS_FAILED,
                    'errors' => $errors,
                ]);

                return;
            }

            // 3. Compute differences against the current active version of RAB
            $currentMaxVersion = RabBudget::where('project_id', $job->project_id)->max('version') ?? 0;

            $activeRabs = RabBudget::where('project_id', $job->project_id)
                ->where('version', $currentMaxVersion)
                ->get()
                ->keyBy(function ($item) {
                    return $this->getUniqueKey($item->code_item, $item->description, $item->category);
                });

            $added = [];
            $updated = [];
            $deleted = [];
            $seenKeys = [];

            foreach ($newItems as $newItem) {
                $key = $this->getUniqueKey($newItem['code_item'], $newItem['description'], $newItem['category'] ?? null);
                $seenKeys[$key] = true;

                if ($activeRabs->has($key)) {
                    $activeItem = $activeRabs->get($key);

                    // Check for changes
                    $hasChanges = false;
                    $changes = [];

                    foreach (['volume', 'unit_price', 'unit', 'category'] as $field) {
                        $oldVal = $activeItem->$field;
                        $newVal = $newItem[$field];

                        if ($field === 'volume' || $field === 'unit_price') {
                            if (abs((float) $oldVal - (float) $newVal) > 0.0001) {
                                $hasChanges = true;
                                $changes[$field] = ['old' => (float) $oldVal, 'new' => (float) $newVal];
                            }
                        } else {
                            if ($oldVal !== $newVal) {
                                $hasChanges = true;
                                $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
                            }
                        }
                    }

                    if ($hasChanges) {
                        $updated[] = [
                            'code_item' => $newItem['code_item'],
                            'description' => $newItem['description'],
                            'changes' => $changes,
                        ];
                    }
                } else {
                    $added[] = [
                        'code_item' => $newItem['code_item'],
                        'description' => $newItem['description'],
                        'volume' => $newItem['volume'],
                        'unit' => $newItem['unit'],
                        'unit_price' => $newItem['unit_price'],
                        'total_price' => $newItem['total_price'],
                    ];
                }
            }

            // Any active item not seen in the new file is marked as deleted
            foreach ($activeRabs as $key => $activeItem) {
                if (! isset($seenKeys[$key])) {
                    $deleted[] = [
                        'code_item' => $activeItem->code_item,
                        'description' => $activeItem->description,
                        'volume' => $activeItem->volume,
                        'unit' => $activeItem->unit,
                        'total_price' => $activeItem->total_price,
                    ];
                }
            }

            $diff = [
                'added_count' => count($added),
                'updated_count' => count($updated),
                'deleted_count' => count($deleted),
                'added' => array_slice($added, 0, 50), // limits diff detail payload size
                'updated' => array_slice($updated, 0, 50),
                'deleted' => array_slice($deleted, 0, 50),
                'available_sheets' => array_map(fn ($s) => $s['sheetName'], $validSheets),
                'selected_sheet' => $job->sheet_name,
            ];

            $job->update([
                'status' => RabImportJob::STATUS_VALIDATED,
                'total_rows' => $rowCount,
                'errors' => [],
                'diff' => $diff,
            ]);

        } catch (\Throwable $e) {
            Log::error('Validation Job error: '.$e->getMessage()."\n".$e->getTraceAsString());
            $job->update([
                'status' => RabImportJob::STATUS_FAILED,
                'errors' => ['Gagal memproses validasi file: '.$e->getMessage()],
            ]);
        }
    }

    private function getUniqueKey(?string $code, string $description, ?string $category = null): string
    {
        $codeClean = strtolower(trim((string) $code));
        $descClean = strtolower(trim($description));
        $catClean = strtolower(trim((string) $category));

        // Bug #5 fix: Include category in key to avoid collision between
        // items with same code/description across different RAB sections
        if ($codeClean !== '') {
            return $catClean !== '' ? $codeClean.'::'.$catClean : $codeClean;
        }

        return md5($descClean.'::'.$catClean);
    }
}
