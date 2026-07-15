<?php

namespace App\Traits;

use App\Services\MimoAiService;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;

trait HandlesRabParsing
{
    protected $codeCounters = [];

    /**
     * Reset code counters between sheet iterations to avoid state leaking.
     */
    protected function resetCodeCounters(): void
    {
        $this->codeCounters = [];
    }

    /**
     * Check if a cell value is effectively empty (null, empty string, or whitespace-only).
     * Handles both null (from some parsers) and '' (from XML streaming).
     */
    protected function isEmptyCell($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * Unified row streamer based on file type.
     */
    protected function streamRows(string $path, string $type, ?string $sheetName = null): \Generator
    {
        $type = strtolower($type);
        if ($type === 'xlsx') {
            foreach ($this->streamWorksheetRows($path, $sheetName ?? 'Sheet1') as $idx => $row) {
                yield $idx => $row;
            }
        } elseif ($type === 'csv') {
            foreach ($this->streamCsvRows($path) as $idx => $row) {
                yield $idx => $row;
            }
        } elseif ($type === 'xls') {
            foreach ($this->streamXlsRows($path) as $idx => $row) {
                yield $idx => $row;
            }
        } else {
            throw new \InvalidArgumentException("Format file tidak didukung: {$type}");
        }
    }

    /**
     * Parse the first N rows of a file for preview/sheet identification.
     */
    /**
     * Read actual sheet names from XLSX workbook.xml
     */
    protected function readSheetNames(\ZipArchive $zip): array
    {
        $namesByNumber = [];
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            return $namesByNumber;
        }

        $workbook = simplexml_load_string($workbookXml);
        if (! $workbook) {
            return $namesByNumber;
        }

        $targetsByRelationship = [];
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relationshipsXml !== false && ($relationships = simplexml_load_string($relationshipsXml))) {
            foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                $attributes = $relationship->attributes();
                $targetsByRelationship[(string) $attributes['Id']] = (string) $attributes['Target'];
            }
        }

        $namespaces = $workbook->getNamespaces(true);
        $relationshipNamespace = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $fallbackNumber = 1;

        foreach ($workbook->xpath('//*[local-name()="sheet"]') ?: [] as $sheet) {
            $name = (string) ($sheet->attributes()['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $relationshipId = (string) ($sheet->attributes($relationshipNamespace)['id'] ?? '');
            $target = str_replace('\\', '/', $targetsByRelationship[$relationshipId] ?? '');
            $number = null;
            if (preg_match('#(?:^|/)worksheets/sheet(\d+)\.xml$#i', $target, $match)) {
                $number = (int) $match[1];
            }

            $namesByNumber[$number ?? $fallbackNumber] = $name;
            $fallbackNumber++;
        }

        ksort($namesByNumber);

        return $namesByNumber;
    }

    protected function parseRaw(string $path, string $type, ?int $maxRows = null, ?string $filterSheet = null): array
    {
        $type = strtolower($type);
        $sheets = [];
        $errors = [];

        if ($type === 'xlsx') {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Tidak dapat membuka file XLSX.');
            }

            $sheetNames = $this->readSheetNames($zip);

            $sheetFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $m)) {
                    $sheetFiles[(int) $m[1]] = $name;
                }
            }
            ksort($sheetFiles);
            if (empty($sheetFiles)) {
                $sheetFiles[1] = 'xl/worksheets/sheet1.xml';
            }
            $zip->close();

            // Skip known non-RAB sheet names for faster parsing
            $skipSheets = ['terbilang', 'kurva', 'curve', 'schedule', 'time schedule', 'cash flow', 'rekap total'];

            foreach ($sheetFiles as $sheetNum => $sheetPath) {
                $sheetName = $sheetNames[$sheetNum] ?? "Sheet$sheetNum";
                $sheetNameLower = strtolower($sheetName);

                // If specific sheet requested, skip others
                if ($filterSheet && strtolower($filterSheet) !== $sheetNameLower) {
                    continue;
                }

                // Skip non-RAB sheets
                $shouldSkip = false;
                foreach ($skipSheets as $skip) {
                    if (str_contains($sheetNameLower, $skip)) {
                        $shouldSkip = true;
                        break;
                    }
                }
                if ($shouldSkip) {
                    continue;
                }

                $sheetRows = [];
                foreach ($this->streamWorksheetRows($path, $sheetName) as $row) {
                    if ($maxRows !== null && count($sheetRows) >= $maxRows) {
                        break;
                    }
                    $sheetRows[] = $row;
                }
                $sheets[$sheetName] = $sheetRows;
            }
        } elseif ($type === 'csv') {
            $sheetRows = [];
            foreach ($this->streamCsvRows($path) as $row) {
                if ($maxRows !== null && count($sheetRows) >= $maxRows) {
                    break;
                }
                $sheetRows[] = $row;
            }
            $sheets['CSV_Data'] = $sheetRows;
        } elseif ($type === 'xls') {
            $sheetRows = [];
            foreach ($this->streamXlsRows($path) as $row) {
                if ($maxRows !== null && count($sheetRows) >= $maxRows) {
                    break;
                }
                $sheetRows[] = $row;
            }
            $sheets['XLS_Data'] = $sheetRows;
        } else {
            throw new \InvalidArgumentException("Format file tidak didukung: {$type}");
        }

        return ['sheets' => $sheets, 'errors' => $errors];
    }

    /**
     * CSV Reader (memory efficient)
     */
    protected function streamCsvRows(string $path): \Generator
    {
        if (($handle = fopen($path, 'r')) !== false) {
            // Detect delimiter
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if (strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false) {
                $delimiter = ';';
            } elseif (strpos($firstLine, "\t") !== false) {
                $delimiter = "\t";
            }

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                yield array_map(fn ($val) => $val !== null ? trim((string) $val) : '', $data);
            }
            fclose($handle);
        }
    }

    /**
     * XLS Reader (via PhpSpreadsheet)
     */
    protected function streamXlsRows(string $path): \Generator
    {
        $reader = IOFactory::createReader('Xls');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = (string) $cell->getValue();
            }
            yield $rowData;
        }
    }

    /**
     * XLSX Reader (raw XML zip streaming)
     */
    /**
     * Resolve sheet name to sheet file number via workbook.xml
     */
    protected function resolveSheetNumber(\ZipArchive $zip, string $sheetName): int
    {
        // If already "SheetN" format, extract directly
        if (preg_match('/^Sheet(\d+)$/', $sheetName, $m)) {
            return (int) $m[1];
        }

        foreach ($this->readSheetNames($zip) as $number => $name) {
            if ($name === $sheetName) {
                return (int) $number;
            }
        }

        return 1; // fallback
    }

    protected function streamWorksheetRows(string $path, string $sheetName): \Generator
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Tidak dapat membuka file XLSX.');
        }
        $sheetNum = $this->resolveSheetNumber($zip, $sheetName);
        $sharedStrings = $this->sharedStrings($zip);
        $zip->close();

        $reader = new \XMLReader;
        $worksheetPath = "zip://{$path}#xl/worksheets/sheet{$sheetNum}.xml";
        if (! $reader->open($worksheetPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            // Fallback to sheet1 if sheetName is custom but not found
            $worksheetPath = "zip://{$path}#xl/worksheets/sheet1.xml";
            if (! $reader->open($worksheetPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new \RuntimeException("Tidak dapat membaca worksheet {$sheetName}.");
            }
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                    continue;
                }

                $rowXml = $reader->readOuterXml();
                if ($rowXml === '') {
                    continue;
                }
                yield $this->rowFromXml($rowXml, $sharedStrings);
            }
        } finally {
            $reader->close();
        }
    }

    protected function sharedStrings(\ZipArchive $zip): array
    {
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml === false) {
            return $sharedStrings;
        }

        $xml = simplexml_load_string($sharedStringsXml);
        if (! $xml) {
            return $sharedStrings;
        }

        foreach ($xml->si as $sharedString) {
            $text = isset($sharedString->t) ? (string) $sharedString->t : '';
            foreach ($sharedString->r as $run) {
                $text .= (string) $run->t;
            }
            $sharedStrings[] = $text;
        }

        return $sharedStrings;
    }

    protected function rowFromXml(string $rowXml, array $sharedStrings): array
    {
        $rowNode = simplexml_load_string($rowXml);
        if (! $rowNode) {
            return [];
        }

        $rowData = [];
        foreach ($rowNode->c as $cell) {
            $reference = (string) $cell['r'];
            $columnIndex = $this->columnLetterToIndex(preg_replace('/\d+/', '', $reference));
            $type = (string) $cell['t'];
            $value = null;

            if ($type === 's') {
                $value = $sharedStrings[(int) $cell->v] ?? '';
            } elseif (isset($cell->v)) {
                $value = (string) $cell->v;
            } elseif (isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            } elseif (isset($cell->is->r)) {
                $value = '';
                foreach ($cell->is->r as $run) {
                    $value .= (string) $run->t;
                }
            }

            while (count($rowData) <= $columnIndex) {
                $rowData[] = '';
            }
            $rowData[$columnIndex] = $value;
        }

        return $rowData;
    }

    protected function columnLetterToIndex(string $letter): int
    {
        $letter = strtoupper($letter);
        $index = 0;
        $len = strlen($letter);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Generate next sequential code based on category.
     */
    protected function getNextAutoCode(?string $category): string
    {
        $cat = trim((string) $category);
        if ($cat === '') {
            $cat = 'ITEM';
        }

        $resCat = $this->detectResourceCategory($cat);
        if ($resCat !== null) {
            $prefix = [
                'Material' => 'M',
                'Upah' => 'T',
                'Alat' => 'A',
                'Subkon' => 'S',
            ][$resCat] ?? 'M';

            if (! isset($this->codeCounters[$prefix])) {
                $this->codeCounters[$prefix] = 1;
            }
            $num = $this->codeCounters[$prefix]++;

            return $prefix.'.'.str_pad($num, 2, '0', STR_PAD_LEFT);
        }

        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cat), 0, 3));
        if ($prefix === '') {
            $prefix = 'ITEM';
        }

        if (! isset($this->codeCounters[$prefix])) {
            $this->codeCounters[$prefix] = 1;
        }

        $num = $this->codeCounters[$prefix]++;

        return $prefix.'.'.str_pad($num, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Strictly validate number format and values.
     */
    protected function validateNumber($value, string $fieldName, int $rowNumber, string $description): ?float
    {
        if ($value === null || $value === '') {
            return 0.0; // Default to 0.0 for empty/null cells
        }

        $s = trim((string) $value);
        if ($s === '' || $s === '-' || $s === '0' || $s === '0,00' || $s === '0.00') {
            return 0.0;
        }

        $s = str_replace(['Rp', 'rp', 'RP', 'Rp.', 'Rp. ', ' '], '', $s);

        // Remove parentheses notation like "(3)" → treat as section indicator → skip
        if (preg_match('/^\(.*\)$/', $s)) {
            return null;
        }

        // Check directly numeric
        if (is_numeric($s)) {
            return (float) $s;
        }

        // Indonesian format: 1.000.000,50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);

            return (float) $s;
        }

        // English format: 1,000,000.50
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);

            return (float) $s;
        }

        // Replace comma with dot
        $sReplaced = str_replace(',', '.', $s);
        if (is_numeric($sReplaced)) {
            return (float) $sReplaced;
        }

        return null; // Invalid text format
    }

    /**
     * Normalize row and return data or validation error array.
     *
     * @param  bool  $useLlm  When false (default), AI LLM classification is skipped during validation to keep imports fast.
     */
    protected function normalizeRabRow(array $row, array $columnMap, int $rowNumber, ?string $currentSectionCode = null, bool $useLlm = false): ?array
    {
        // Empty rows are ignored. Description + total rows may be valid lump-sum items.
        $nonEmpty = count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));
        if ($nonEmpty === 0) {
            return null;
        }

        $description = trim((string) ($row[$columnMap['uraian']] ?? ''));

        $rawVolume = $row[$columnMap['volume'] ?? -1] ?? null;
        $rawUnitPrice = $row[$columnMap['harga_satuan'] ?? -1] ?? null;
        $rawTotalPrice = $row[$columnMap['jumlah'] ?? -1] ?? null;

        // Skip completely empty rows (handle both null and '' from different parsers)
        if ($description === '' && $this->isEmptyCell($rawVolume) && $this->isEmptyCell($rawUnitPrice) && $this->isEmptyCell($rawTotalPrice)) {
            return null;
        }

        // Header or section row indicators — skip silently (merged cells cause empty descriptions)
        if ($description === '' || strtolower($description) === 'jumlah' || strtolower($description) === 'total' || strtolower($description) === 'subtotal') {
            return null; // Skip rows without valid description — merged cells, totals, section headers
        }

        // Numbering rows immediately below a multi-level header ("1 | 2 | 3 ...")
        // are structural labels, not RAB items.
        if (preg_match('/^\d+(?:\.\d+)?$/', $description)) {
            $allShortTokens = true;
            foreach ($row as $value) {
                $token = trim((string) $value);
                if ($token !== '' && strlen($token) > 15) {
                    $allShortTokens = false;
                    break;
                }
            }
            if ($allShortTokens) {
                return null;
            }
        }

        if (! empty($columnMap['_require_numbered_item'])) {
            $number = trim((string) ($row[$columnMap['no'] ?? -1] ?? ''));
            if (! preg_match('/^\d+(?:[.\-]\d+)*$/', $number)) {
                return null;
            }
        }

        // Section headings (description is filled, but volume and price are completely empty)
        // Use isEmptyCell() to handle both null and '' from XML streaming
        if ($this->isEmptyCell($rawVolume) && $this->isEmptyCell($rawUnitPrice) && $this->isEmptyCell($rawTotalPrice)) {
            return null; // Skip section headings
        }

        // Skip total/summary rows by description pattern
        $descLower = strtolower($description);
        if ($description === '-' || str_contains($descLower, 'total') || str_contains($descLower, 'subtotal') || str_contains($descLower, 'sub total') || str_contains($descLower, 'grand total') || str_contains($descLower, 'jumlah keseluruhan') || str_contains($descLower, 'total nilai') || str_contains($descLower, 'total penawaran') || str_contains($descLower, 'total harga')) {
            return null;
        }

        // Strict numeric validation
        $rawVolStr = trim((string) ($rawVolume ?? ''));

        // Skip rows with empty/whitespace/dash volume — but NOT volume=0 with total_price
        // (Lump Sum items may have volume=0 or empty but still have a total price)
        if ($rawVolStr === '' || $rawVolStr === '-') {
            // Check if this might be a Lump Sum item (has total_price)
            $lsTotalPrice = $this->validateNumber($rawTotalPrice, 'Jumlah', $rowNumber, $description);
            if ($lsTotalPrice !== null && $lsTotalPrice > 0) {
                // Treat as Lump Sum: volume=1, unit_price=total_price
                $volume = 1.0;
                $unitPrice = $lsTotalPrice;
                $totalPrice = $lsTotalPrice;
                $isLumpSum = true;
            } else {
                return null;
            }
        }

        if (! isset($isLumpSum)) {
            $isLumpSum = false;

            // Validate volume is numeric
            $volume = $this->validateNumber($rawVolume, 'Volume', $rowNumber, $description);
            if ($volume === null) {
                // Sub-header patterns: single letter, parentheses like "(3)", short labels
                if (strlen($rawVolStr) <= 1 || preg_match('/^\(.+\)$/', $rawVolStr)) {
                    return null;
                }

                // Longer non-numeric volume = invalid data, return error
                return ['error' => "Baris {$rowNumber} ({$description}): Volume '{$rawVolume}' tidak valid. Harus berupa angka."];
            }

            $unitPrice = $this->validateNumber($rawUnitPrice, 'Harga Satuan', $rowNumber, $description);
            if ($unitPrice === null) {
                // Non-numeric harga_satuan = invalid data, skip row
                return null;
            }

            $totalPrice = $this->validateNumber($rawTotalPrice, 'Jumlah', $rowNumber, $description);
            if ($totalPrice === null) {
                return ['error' => "Baris {$rowNumber} ({$description}): Total Harga (Jumlah) '{$rawTotalPrice}' tidak valid. Harus berupa angka."];
            }

            // Skip if everything numeric is zero/null (likely metadata or section footer)
            if ($volume === 0.0 && $unitPrice === 0.0 && $totalPrice === 0.0) {
                return null;
            }

            // Handle volume=0 with total_price > 0 as Lump Sum
            if ($volume <= 0 && $totalPrice > 0) {
                $volume = 1.0;
                $unitPrice = $totalPrice;
                $isLumpSum = true;
            } elseif ($volume <= 0) {
                return null;
            }
        }
        if ($unitPrice < 0) {
            return ['error' => "Baris {$rowNumber} ({$description}): Harga Satuan harus bernilai 0 atau lebih."];
        }

        if ($totalPrice === 0.0) {
            $totalPrice = $volume * $unitPrice;
        }
        // Calculate unit_price from total_price / volume when unit_price is 0
        if ($unitPrice === 0.0 && $volume > 0 && $totalPrice > 0) {
            $unitPrice = $totalPrice / $volume;
        }
        if ($totalPrice < 0) {
            return ['error' => "Baris {$rowNumber} ({$description}): Jumlah tidak boleh negatif."];
        }

        $category = trim((string) ($row[$columnMap['kategori'] ?? -1] ?? '')) ?: null;

        // Auto-classify if no category column or empty
        if ($category === null) {
            $category = $this->autoClassifyCategory($description, $useLlm);
        }

        $code = trim((string) ($row[$columnMap['kode'] ?? -1] ?? ''));
        if ($code === '') {
            $parentCode = trim((string) $currentSectionCode);
            if ($parentCode !== '') {
                $prefix = rtrim($parentCode, '.');
                if (! isset($this->codeCounters[$prefix])) {
                    $this->codeCounters[$prefix] = 1;
                }
                $num = $this->codeCounters[$prefix]++;
                $code = $prefix.'.'.str_pad($num, 2, '0', STR_PAD_LEFT);
            } else {
                $code = $this->getNextAutoCode($category);
            }
        }

        $unit = trim((string) ($row[$columnMap['satuan'] ?? -1] ?? ''));
        // If detected as Lump Sum and unit is empty, set unit to 'LS'
        if ($isLumpSum && ($unit === '' || $unit === '-')) {
            $unit = 'LS';
        }

        return [
            'code_item' => $code,
            'description' => $description,
            'unit' => $unit,
            'volume' => $volume,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'category' => $category,
        ];
    }

    /**
     * Map header columns.
     */
    protected function mapColumns(array $headerRow): array
    {
        $map = [];
        $scores = [];

        // ── KEYWORD-BASED DETECTION ──────────────────────────────────
        // Score each header cell against keywords for each column type.
        // Works with ANY RAB format — no hardcoded aliases needed.
        $colKeywords = [
            'no' => ['no', 'nomor', 'urut', 'seq', 'number'],
            'kode' => ['kode', 'code', 'kd', 'analis', 'analisa', 'ref', 'referensi', 'rekening'],
            'uraian' => ['uraian', 'deskripsi', 'description', 'pekerjaan', 'barang', 'jasa', 'nama', 'rincian', 'keterangan', 'item', 'material'],
            'volume' => ['volume', 'vol', 'qty', 'quantity', 'kuantitas', 'koef', 'koefisien', 'banyak', 'perkiraan'],
            'satuan' => ['satuan', 'uom', 'sat'],
            'harga_satuan' => ['harga satuan', 'unit price', 'harga per', 'hsp', 'hsat', 'harga unit', 'harga'],
            'jumlah' => ['jumlah harga', 'total harga', 'total biaya', 'subtotal', 'nilai', 'total', 'biaya', 'amount', 'jumlah'],
            'kategori' => ['kategori', 'kelompok', 'category', 'group', 'golongan', 'tipe', 'type', 'asal negara', 'tkdn'],
        ];

        foreach ($headerRow as $colIdx => $header) {
            if ($header === null) {
                continue;
            }
            $h = strtolower(trim((string) $header));
            $h = trim(preg_replace('/\s*\(.*?\)\s*/', '', $h));
            if ($h === '') {
                continue;
            }

            // Normalize whitespace: "j u m l a h" -> "jumlah"
            $hNormalized = preg_replace('/\s+/', '', $h);

            $bestScore = 0;
            $bestKey = null;

            foreach ($colKeywords as $key => $keywords) {
                foreach ($keywords as $kw) {
                    $kw = strtolower($kw);
                    $kwNormalized = preg_replace('/\s+/', '', $kw);
                    if ($h === $kw || $hNormalized === $kwNormalized) {
                        $score = 1000;
                    } elseif (str_starts_with($h, $kw) || str_starts_with($hNormalized, $kwNormalized)) {
                        $score = 800;
                    } elseif (preg_match('/\b'.preg_quote($kw, '/').'\b/', $h) || str_contains($hNormalized, $kw)) {
                        $score = 500;
                    } elseif (str_contains($h, $kw)) {
                        $score = 200;
                    } else {
                        continue;
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestKey = $key;
                    }
                }
            }

            // Bug #6 fix: Disambiguate "jumlah" — if header says "jumlah volume",
            // "jumlah qty", or "jumlah kuantitas", it's a volume column, not total price.
            $hasVolumeHint = preg_match('/volume|qty|quantity|kuantitas|koef|banyak|perkiraan/', $hNormalized) === 1;
            if (preg_match('/^(uraian|deskripsi|description|namabarang|namapekerjaan)/', $hNormalized)) {
                $bestKey = 'uraian';
                $bestScore = 1200;
            } elseif (str_starts_with($hNormalized, 'rincian')) {
                $bestKey = 'uraian';
                $bestScore = max($bestScore, 1050);
            } elseif ($hasVolumeHint) {
                $bestKey = 'volume';
                $bestScore = max($bestScore, 900);
            } elseif (str_contains($hNormalized, 'jumlah') || str_contains($hNormalized, 'total') || str_contains($hNormalized, 'amount')) {
                $bestKey = 'jumlah';
                $bestScore = max($bestScore, 900);
            } elseif (str_contains($hNormalized, 'harga') && ! str_contains($hNormalized, 'jumlah')) {
                $bestKey = 'harga_satuan';
                $bestScore = max($bestScore, 700);
            }

            if ($bestKey !== null) {
                if (! isset($map[$bestKey]) || $bestScore > ($scores[$bestKey] ?? 0)) {
                    $map[$bestKey] = $colIdx;
                    $scores[$bestKey] = $bestScore;
                }
            }
        }

        // ── POSITIONAL FALLBACK ──────────────────────────────────────
        // If keyword detection missed critical columns, guess by position.
        // Standard RAB order: No | Kode/Uraian | ... | Volume | Satuan | Harga Satuan | Jumlah
        $nonEmptyCols = [];
        foreach ($headerRow as $colIdx => $header) {
            if ($header !== null && trim((string) $header) !== '') {
                $nonEmptyCols[] = $colIdx;
            }
        }

        if (count($nonEmptyCols) >= 3) {
            // If no uraian detected, assume first text-like column is uraian
            if (! isset($map['uraian'])) {
                foreach ($nonEmptyCols as $idx) {
                    $val = trim((string) ($headerRow[$idx] ?? ''));
                    if (! is_numeric($val) && strlen($val) > 2) {
                        $map['uraian'] = $idx;
                        break;
                    }
                }
            }
        }

        // ── ADAPTIVE FALLBACKS ─────────────────────────────────────────
        // 1. If volume missing + jumlah exists + harga_satuan exists → jumlah is volume
        // 2. If volume missing + jumlah exists → jumlah is volume
        // 3. If jumlah missing + look for 'Harga' column (total price) that wasn't mapped
        if (! isset($map['jumlah'])) {
            foreach ($headerRow as $colIdx => $header) {
                if ($header === null) {
                    continue;
                }
                if (in_array($colIdx, $map)) {
                    continue;
                } // already mapped
                $h = strtolower(trim((string) $header));
                if (str_contains($h, 'harga') && ! str_contains($h, 'satuan')) {
                    $map['jumlah'] = $colIdx;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Move a mapped header to the data-bearing column inside its merged span.
     * A merged B:D "Uraian" header, for example, often stores values in D.
     */
    protected function refineColumnMap(array $headerRow, array $dataRows, array $map): array
    {
        $headerColumns = [];
        foreach ($headerRow as $index => $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $headerColumns[] = (int) $index;
            }
        }
        sort($headerColumns);

        foreach (['uraian', 'satuan', 'volume', 'harga_satuan', 'jumlah', 'kode'] as $field) {
            if (! isset($map[$field])) {
                continue;
            }

            $start = (int) $map[$field];
            $end = $start;
            foreach ($headerColumns as $headerColumn) {
                if ($headerColumn > $start) {
                    $end = min($headerColumn - 1, $start + 4);
                    break;
                }
            }
            if ($end <= $start) {
                continue;
            }

            $bestColumn = $start;
            $bestScore = -INF;
            for ($column = $start; $column <= $end; $column++) {
                $nonEmpty = 0;
                $numeric = 0;
                $text = 0;
                $textLength = 0;

                foreach (array_slice($dataRows, 0, 80) as $row) {
                    $value = trim((string) ($row[$column] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $nonEmpty++;
                    if ($this->validateNumber($value, $field, 0, '') !== null) {
                        $numeric++;
                    } else {
                        $text++;
                        $textLength += min(strlen($value), 80);
                    }
                }

                $score = match ($field) {
                    'uraian' => ($text * 30) + $textLength - ($numeric * 20),
                    'satuan' => ($text * 20) - ($numeric * 10) - $textLength,
                    'volume', 'harga_satuan', 'jumlah' => ($numeric * 30) - ($text * 20),
                    'kode' => ($nonEmpty * 10) - ($textLength / 10),
                    default => $nonEmpty,
                };

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestColumn = $column;
                }
            }

            $map[$field] = $bestColumn;
        }

        return $map;
    }

    /**
     * Identify best sheet for import.
     */
    protected function findBestSheet(array $sheets): ?array
    {
        $selected = $this->selectImportSheets($this->buildSheetCandidates($sheets));
        if ($selected !== []) {
            return $selected[0];
        }

        $headerKeywords = ['uraian', 'deskripsi', 'pekerjaan', 'description', 'item', 'nama barang', 'uraian barang', 'harga satuan', 'volume', 'satuan', 'harga', 'qty', 'jumlah', 'total', 'material', 'bahan', 'upah', 'tenaga', 'alat', 'subkon', 'kode', 'no', 'biaya', 'cost'];
        $requiredCols = ['uraian', 'volume', 'harga_satuan'];
        $best = null;
        $bestScore = 0;

        foreach ($sheets as $sheetName => $rows) {
            $headerRowIndex = null;

            foreach ($rows as $idx => $row) {
                $lower = array_map(fn ($v) => strtolower(trim((string) $v)), $row);
                $matchCount = 0;
                foreach ($lower as $cell) {
                    foreach ($headerKeywords as $keyword) {
                        if (str_contains($cell, $keyword)) {
                            $matchCount++;
                            break;
                        }
                    }
                }
                if ($matchCount >= 1) {
                    $headerRowIndex = $idx;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                foreach ($rows as $idx => $row) {
                    $lower = array_map(fn ($v) => strtolower(trim((string) $v)), $row);
                    foreach ($lower as $cell) {
                        if (str_contains($cell, 'uraian') || str_contains($cell, 'harga satuan') || str_contains($cell, 'deskripsi') || str_contains($cell, 'volume') || str_contains($cell, 'harga') || str_contains($cell, 'satuan') || str_contains($cell, 'qty') || str_contains($cell, 'jumlah')) {
                            $headerRowIndex = $idx;
                            break 2;
                        }
                    }
                }
            }

            // Last resort: positional detection (assume first row with 3+ non-empty cells is header)
            if ($headerRowIndex === null) {
                foreach ($rows as $idx => $row) {
                    $nonEmpty = count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''));
                    if ($nonEmpty >= 3) {
                        $headerRowIndex = $idx;
                        break;
                    }
                }
            }

            if ($headerRowIndex === null) {
                continue;
            }

            $colMap = $this->mapColumns($rows[$headerRowIndex]);
            if (! isset($colMap['uraian'])) {
                continue;
            }

            if (! isset($colMap['volume']) || ! isset($colMap['harga_satuan'])) {
                for ($next = $headerRowIndex + 1; $next < min($headerRowIndex + 3, count($rows)); $next++) {
                    $colMap2 = $this->mapColumns($rows[$next]);
                    if (isset($colMap2['volume']) && ! isset($colMap['volume'])) {
                        $colMap['volume'] = $colMap2['volume'];
                    }
                    if (isset($colMap2['harga_satuan']) && ! isset($colMap['harga_satuan'])) {
                        $colMap['harga_satuan'] = $colMap2['harga_satuan'];
                    }
                    if (isset($colMap2['jumlah']) && ! isset($colMap['jumlah'])) {
                        $colMap['jumlah'] = $colMap2['jumlah'];
                    }
                    if (isset($colMap['volume']) && isset($colMap['harga_satuan'])) {
                        break;
                    }
                }
            }

            $requiredCount = 0;
            foreach ($requiredCols as $col) {
                if (isset($colMap[$col])) {
                    $requiredCount++;
                }
            }
            if ($requiredCount === 0) {
                continue;
            }

            $dataRows = 0;
            $numericRows = 0;
            foreach ($rows as $idx => $row) {
                if ($idx <= $headerRowIndex) {
                    continue;
                }
                $uraian = trim((string) ($row[$colMap['uraian']] ?? ''));
                if ($uraian !== '' && strtolower($uraian) !== 'jumlah' && strtolower($uraian) !== 'total') {
                    $dataRows++;
                    $vol = $this->validateNumber($row[$colMap['volume'] ?? -1] ?? null, 'Volume', $idx, $uraian);
                    $harga = $this->validateNumber($row[$colMap['harga_satuan'] ?? -1] ?? null, 'Harga Satuan', $idx, $uraian);
                    if ($vol !== null && $harga !== null) {
                        $numericRows++;
                    }
                }
            }

            if ($dataRows === 0) {
                continue;
            }

            $qualityScore = $requiredCount * 1000 + $numericRows;
            if ($qualityScore > $bestScore || ($qualityScore === $bestScore && ($best === null || $dataRows > $best['dataRows']))) {
                $best = [
                    'sheetName' => $sheetName,
                    'rows' => $rows,
                    'headerIndex' => $headerRowIndex,
                    'colMap' => $colMap,
                    'dataRows' => $dataRows,
                    'requiredCount' => $requiredCount,
                    'numericRows' => $numericRows,
                ];
                $bestScore = $qualityScore;
            }
        }

        return $best;
    }

    /**
     * Heuristically determine if a sheet is a non-RAB sheet (e.g., AHS, DHBU, Rekap, TKDN, Schedule).
     */
    protected function buildSheetCandidates(array $sheets): array
    {
        $candidates = [];
        $sheetOrder = 0;

        foreach ($sheets as $sheetName => $rows) {
            $best = null;
            $limit = min(count($rows), 60);

            for ($index = 0; $index < $limit; $index++) {
                $header = $rows[$index] ?? [];
                $headerIndex = $index;

                if (isset($rows[$index + 1]) && $this->isLikelyHeaderContinuation($rows[$index + 1])) {
                    $header = $this->combineHeaderRows($header, $rows[$index + 1]);
                    $headerIndex = $index + 1;
                }

                $map = $this->mapColumns($header);
                if (! isset($map['uraian'])) {
                    continue;
                }
                if (! isset($map['volume']) && ! isset($map['jumlah'])) {
                    continue;
                }
                if (! isset($map['harga_satuan']) && ! isset($map['jumlah'])) {
                    continue;
                }

                $previewDataRows = array_slice($rows, $headerIndex + 1, 100);
                $map = $this->refineColumnMap($header, $previewDataRows, $map);
                $metrics = $this->measureCandidateRows($previewDataRows, $map);
                if ($metrics['coherentRows'] === 0) {
                    continue;
                }

                $fieldCount = count(array_intersect(
                    ['uraian', 'volume', 'satuan', 'harga_satuan', 'jumlah', 'kode'],
                    array_keys($map)
                ));
                $qualityScore = ($fieldCount * 1000) + ($metrics['coherentRows'] * 20) + $metrics['descriptionRows'];

                if ($best === null || $qualityScore > $best['qualityScore']) {
                    $best = [
                        'sheetName' => $sheetName,
                        'rows' => $rows,
                        'headerIndex' => $headerIndex,
                        'colMap' => $map,
                        'dataRows' => $metrics['coherentRows'],
                        'numericRows' => $metrics['numericRows'],
                        'numberedRows' => $metrics['numberedRows'],
                        'unnumberedRows' => $metrics['unnumberedRows'],
                        'requiredCount' => $fieldCount,
                        'qualityScore' => $qualityScore,
                        'sheetOrder' => $sheetOrder,
                    ];
                }
            }

            if ($best !== null) {
                $candidates[] = $best;
            }
            $sheetOrder++;
        }

        return $candidates;
    }

    protected function combineHeaderRows(array $first, array $second): array
    {
        $width = max(count($first), count($second));
        $combined = [];
        for ($column = 0; $column < $width; $column++) {
            $top = trim((string) ($first[$column] ?? ''));
            $bottom = trim((string) ($second[$column] ?? ''));
            $combined[$column] = trim($top.' '.$bottom);
        }

        return $combined;
    }

    protected function isLikelyHeaderContinuation(array $row): bool
    {
        $tokens = [];
        foreach ($row as $value) {
            $token = trim((string) $value);
            if ($token === '') {
                continue;
            }
            if (is_numeric($token) && abs((float) $token) > 100) {
                return false;
            }
            if (strlen($token) > 30) {
                return false;
            }
            $tokens[] = $token;
        }

        if (count($tokens) < 2) {
            return false;
        }

        $structural = 0;
        foreach ($tokens as $token) {
            if (preg_match('/^[a-z0-9.()=+*\/\-\s]{1,15}$/i', $token)) {
                $structural++;
            }
        }

        return $structural / count($tokens) >= 0.6;
    }

    protected function measureCandidateRows(array $rows, array $map): array
    {
        $descriptionRows = 0;
        $numericRows = 0;
        $coherentRows = 0;
        $numberedRows = 0;
        $unnumberedRows = 0;

        foreach ($rows as $row) {
            $description = trim((string) ($row[$map['uraian']] ?? ''));
            if ($description === '' || preg_match('/^\d+(?:\.\d+)?$/', $description)) {
                continue;
            }

            $descriptionLower = strtolower($description);
            if (preg_match('/\b(?:subtotal|grand total|jumlah keseluruhan|total harga)\b/', $descriptionLower)) {
                continue;
            }
            $descriptionRows++;

            $volume = $this->validateNumber($row[$map['volume'] ?? -1] ?? null, 'Volume', 0, $description);
            $price = $this->validateNumber($row[$map['harga_satuan'] ?? -1] ?? null, 'Harga', 0, $description);
            $total = $this->validateNumber($row[$map['jumlah'] ?? -1] ?? null, 'Jumlah', 0, $description);
            $hasNumeric = ($volume !== null && $volume > 0)
                || ($price !== null && $price > 0)
                || ($total !== null && $total > 0);

            if ($hasNumeric) {
                $numericRows++;
                $coherentRows++;
                $number = trim((string) ($row[$map['no'] ?? -1] ?? ''));
                if ($number !== '' && preg_match('/^\d+(?:[.\-]\d+)*$/', $number)) {
                    $numberedRows++;
                } else {
                    $unnumberedRows++;
                }
            }
        }

        return compact('descriptionRows', 'numericRows', 'coherentRows', 'numberedRows', 'unnumberedRows');
    }

    protected function selectImportSheets(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        usort($candidates, fn ($a, $b) => ($a['sheetOrder'] ?? 0) <=> ($b['sheetOrder'] ?? 0));

        $primary = array_values(array_filter($candidates, function ($candidate) {
            $name = strtolower(trim($candidate['sheetName']));

            return preg_match('/(^|[^a-z0-9])rab([^a-z0-9]|$)/i', $name)
                && ! $this->isDerivativeSheetName($name);
        }));
        if ($primary !== []) {
            return $primary;
        }

        $regular = array_values(array_filter($candidates, function ($candidate) {
            $name = strtolower($candidate['sheetName']);

            return ! str_contains($name, 'rekap') && ! $this->isDerivativeSheetName($name);
        }));

        $hierarchicalRecaps = array_values(array_filter($candidates, function ($candidate) {
            $name = strtolower($candidate['sheetName']);

            return str_contains($name, 'rekap')
                && ($candidate['dataRows'] ?? 0) >= 20
                && ($candidate['numberedRows'] ?? 0) >= 5
                && ($candidate['unnumberedRows'] ?? 0) >= 5;
        }));
        if ($hierarchicalRecaps !== []) {
            usort($hierarchicalRecaps, fn ($a, $b) => ($b['dataRows'] ?? 0) <=> ($a['dataRows'] ?? 0));
            $selectedRecap = $hierarchicalRecaps[0];
            $selectedRecap['colMap']['_require_numbered_item'] = true;

            return [$selectedRecap];
        }

        if (count($regular) >= 2) {
            $largest = max(array_column($regular, 'dataRows'));
            $minimum = max(1, (int) floor($largest * 0.05));

            return array_values(array_filter($regular, fn ($candidate) => ($candidate['dataRows'] ?? 0) >= $minimum));
        }

        $detailedRecaps = array_values(array_filter($candidates, function ($candidate) {
            $name = strtolower($candidate['sheetName']);

            return str_contains($name, 'rekap')
                && ! $this->isDerivativeSheetName($name)
                && ($candidate['dataRows'] ?? 0) >= 20;
        }));
        if ($detailedRecaps !== []) {
            usort($detailedRecaps, fn ($a, $b) => ($b['dataRows'] ?? 0) <=> ($a['dataRows'] ?? 0));

            return [$detailedRecaps[0]];
        }

        if ($regular !== []) {
            return $regular;
        }

        usort($candidates, fn ($a, $b) => ($b['qualityScore'] ?? 0) <=> ($a['qualityScore'] ?? 0));

        return [$candidates[0]];
    }

    protected function isDerivativeSheetName(string $sheetName): bool
    {
        $name = strtolower(trim($sheetName));
        $patterns = [
            '/\btkdn\b/', '/\bimpor(?:t)?\b/', '/\banal(?:isa|ysis)?\b/', '/\bahs\b/',
            '/\bdhbu\b/', '/\bdhsp\b/', '/\bschd\b/', '/\bschedule\b/', '/\bkurva\b/',
            '/\bcash\s*flow\b/', '/\brincian\s+tetap\b/', '/\bharga\s+(?:upah|bahan)\b/',
            '/\bmaterial\s+langsung\b/', '/\bbarang\s+jadi\b/', '/\bmanajemen\s+proyek\b/',
            '/\bperolehan\s+alat\b/', '/\bupah\s*\+\s*material\b/', '/\bsmk3\b/',
            '/^ts$/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    protected function isNonRabSheet(string $sheetName, array $rows, int $headerRowIndex): bool
    {
        $nameLower = strtolower($sheetName);

        // Only skip sheets that are CLEARLY not RAB data
        $skipNames = ['terbilang', 'kurva', 'curve', 'schedule', 'time schedule', 'cash flow', 'rekap total', 'rekapitulasi', 'analisa', 'ahs', 'dhbu'];
        foreach ($skipNames as $skip) {
            if ($nameLower === $skip || str_contains($nameLower, $skip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if a section header represents a resource category (Material/Upah/Alat/Subkon).
     */
    protected function detectResourceCategory(string $description): ?string
    {
        $desc = strtolower(trim($description));
        if (str_contains($desc, 'bahan') || str_contains($desc, 'material')) {
            return 'Material';
        }
        if (str_contains($desc, 'upah') || str_contains($desc, 'tenaga kerja') || str_contains($desc, 'tenaga') || str_contains($desc, 'pekerja')) {
            return 'Upah';
        }
        if (str_contains($desc, 'alat') || str_contains($desc, 'peralatan') || str_contains($desc, 'mesin')) {
            return 'Alat';
        }
        if (str_contains($desc, 'subkon') || str_contains($desc, 'subkontraktor') || str_contains($desc, 'sub kontraktor')) {
            return 'Subkon';
        }

        return null;
    }

    /**
     * Hierarchical recap sheets often put a Roman numeral section code in the
     * "No" column and the section subtotal in the amount column. Those rows
     * still define the category even though they are not numerically empty.
     */
    protected function isNumberedRecapSectionRow(array $row, array $columnMap, string $description): bool
    {
        if (empty($columnMap['_require_numbered_item']) || $description === '') {
            return false;
        }

        $number = trim((string) ($row[$columnMap['no'] ?? -1] ?? ''));

        return preg_match('/^[IVXLCDM]+[.\-)]?$/i', $number) === 1;
    }

    protected function isNumberedRecapItemRow(array $row, array $columnMap): bool
    {
        if (empty($columnMap['_require_numbered_item'])) {
            return false;
        }

        $number = trim((string) ($row[$columnMap['no'] ?? -1] ?? ''));

        return preg_match('/^\d+(?:[.\-]\d+)*$/', $number) === 1;
    }

    /**
     * Call AI service (MiMo via OpenRouter or Gemini fallback) to classify construction item.
     * Uses MimoAiService with built-in caching.
     */
    protected function classifyWithLLM(string $description): ?string
    {
        try {
            $service = app(MimoAiService::class);

            return $service->classify($description);
        } catch (\Throwable $e) {
            // Fallback to legacy env-based LLM if service not configured
            $provider = strtolower(env('LLM_PROVIDER', ''));
            $key = env('LLM_API_KEY') ?: env('GEMINI_API_KEY');
            if (! $provider || ! $key) {
                return null;
            }

            $model = env('LLM_MODEL');
            $categories = [
                'Pekerjaan Persiapan', 'Pekerjaan Tanah', 'Pekerjaan Pondasi',
                'Pekerjaan Beton', 'Pekerjaan Batu', 'Pekerjaan Besi',
                'Pekerjaan Kayu', 'Pekerjaan Atap', 'Pekerjaan Plafon',
                'Pekerjaan Lantai', 'Pekerjaan Dinding', 'Pekerjaan Cat',
                'Pekerjaan Sanitari', 'Pekerjaan Mekanikal', 'Pekerjaan Elektrikal',
                'Pekerjaan Bongkar', 'Pekerjaan Landscape', 'Pekerjaan Mebelair',
            ];

            $prompt = "Klasifikasikan deskripsi pekerjaan konstruksi berikut ke salah satu kategori standar ini saja:\n".
                      implode(', ', $categories)."\n\n".
                      "Deskripsi: \"{$description}\"\n\n".
                      'Aturan: Kembalikan HANYA nama kategori yang paling cocok secara persis (case-sensitive) tanpa tanda baca, penjelasan, atau teks tambahan apapun. Jika ragu atau tidak ada yang cocok, kembalikan teks "Lainnya".';

            try {
                if ($provider === 'gemini') {
                    $model = $model ?: 'gemini-2.5-flash';
                    $response = Http::timeout(3)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                            'contents' => [['parts' => [['text' => $prompt]]]],
                        ]);
                    if ($response->successful()) {
                        $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
                    }
                }
                if (isset($text)) {
                    foreach ($categories as $cat) {
                        if (strcasecmp($text, $cat) === 0) {
                            return $cat;
                        }
                    }
                }
            } catch (\Throwable $ex) {
                // Silently fail
            }

            return null;
        }
    }

    /**
     * Auto-classify RAB item into construction work category based on description.
     * Uses keyword scoring — highest score wins. Returns category string or null.
     *
     * @param  bool  $useLlm  When false (default), skip the LLM and only use keyword fallback.
     */
    protected function autoClassifyCategory(string $description, bool $useLlm = false): ?string
    {
        $desc = strtolower(trim($description));
        if ($desc === '') {
            return null;
        }

        if ($useLlm) {
            $llmResult = $this->classifyWithLLM($description);
            if ($llmResult !== null) {
                return $llmResult;
            }
        }

        $categories = [
            'Pekerjaan Persiapan' => [
                'pembersihan', 'clearing', 'pemotongan pohon', 'pemagaran', 'hoarding',
                'mobilisasi', 'demobilisasi', 'papan nama', 'barak', 'gudang',
            ],
            'Pekerjaan Tanah' => [
                'galian', 'urugan', 'tanah', 'cut and fill', 'timbunan', 'pemadatan',
                'tanah urug', 'sirtu', 'screeding', 'grading', 'excavation',
                'gali tanah', 'urug tanah', 'tanah merah', 'tanah biasa', 'pasir urug',
                'stukadoor tanah', 'pembuangan tanah', 'boring', 'bor pile',
            ],
            'Pekerjaan Pondasi' => [
                'pondasi', 'footplate', 'foot plat', 'bored pile', 'tiang pancang',
                'sumuran', 'straus', 'pile cap', 'sloof', 'balok sloof',
                'kompensasi', 'anak tiang', 'piles',
            ],
            'Pekerjaan Beton' => [
                'beton', 'cor', 'readymix', 'ready mix', 'mutu beton', 'bekisting',
                'bekisting', 'kayu bekisting', 'begisting', 'balok', 'kolom',
                'plat lantai', 'ring balk', 'ringbalok', 'kolom praktis',
                'tangga beton', 'dak beton', 'struktur beton', 'adukan beton',
                'screed', 'stuktur', 'stuktural',
            ],
            'Pekerjaan Batu' => [
                'batu bata', 'pasangan batu', 'bata ringan', 'bata merah', 'batako',
                'hebel', 'bata expose', 'batu alam', 'batu kali', 'batu pondasi',
                'plesteran', 'acian', 'nat', 'grouting', 'mortar',
            ],
            'Pekerjaan Besi' => [
                'besi', 'steel', 'baja', 'reinforcement', 'tulangan', 'sengkang',
                'begel', 'batang', 'wiremesh', 'dowel', 'anchor bolt',
                'hollow', 'kanal', 'cnp', 'wf', 'besi siku', 'plat besi',
                'rangka atap baja', 'struktur baja', 'baja ringan',
            ],
            'Pekerjaan Kayu' => [
                'kayu', 'wood', 'papan', 'multipleks', 'triplek', 'plywood',
                'kasau', 'reng', 'balok kayu', 'kosen', 'kusen', 'pintu',
                'jendela', 'daun pintu', 'daun jendela', 'engsel', 'kunci',
                'handle', 'grendel', 'lambersering', 'parquet', 'parket',
                'lantai kayu', 'decking',
            ],
            'Pekerjaan Atap' => [
                'atap', 'genteng', 'seng', 'spandek', 'trimdek', 'roofing',
                'atap seng', 'atap beton', 'atap metal', 'atap zincalume',
                'atap upvc', 'atap polycarbonate', 'talang', 'nok', 'lisplang',
                'karpus', 'atap sirap', 'atap rumbia',
            ],
            'Pekerjaan Plafon' => [
                'plafon', 'ceiling', 'gypsum', 'gipsum', 'rangka plafon',
                'partisi', 'pvc plafon', 'akustik', 'acoustic', 'fiber',
                'kalsiboard', 'grc board', 'rangka hollow',
            ],
            'Pekerjaan Lantai' => [
                'keramik', 'granit', 'marmer', 'ubin', 'lantai', 'flooring',
                'ubin lantai', 'homogeneous', 'vinyl', 'epoxy lantai',
                'penutup lantai', 'nat keramik', 'skirting', 'plint',
                'mozaik', 'teraso', 'paving', 'kanstin', 'conblock',
            ],
            'Pekerjaan Dinding' => [
                'dinding', 'tembok', 'wall', 'partisi', 'cladding',
                'dinding kaca', 'curtain wall', 'facades', 'panel dinding',
                'acp', 'aluminium composite', 'kaca', 'glass',
            ],
            'Pekerjaan Cat' => [
                'cat', 'paint', 'pengecatan', 'mengecat', 'dempul', 'plamir',
                'wallpaper', 'wallcovering', 'anti bocor', 'waterproofing',
                'cat minyak', 'cat tembok', 'cat besi', 'cat kayu',
                'melamic', 'vernis', 'politur', 'coating',
            ],
            'Pekerjaan Sanitari' => [
                'sanitari', 'sanitary', 'closet', 'toilet', 'kloset', 'urinoir',
                'wastafel', 'shower', 'bak mandi', 'kran', 'faucet',
                'saluran air', 'pipa air', 'plumbing', 'drainase', 'got',
                'floor drain', 'grease trap', 'septictank', 'septic tank',
                'sumur resapan', 'toren', 'tangki air', 'pompa air',
            ],
            'Pekerjaan Mekanikal' => [
                'mekanikal', 'ac', 'air conditioning', 'hvac', 'ahu', 'fcu',
                'kompresor', 'chiller', 'cooling tower', 'ventilasi',
                'exhaust fan', 'ducting', 'instalasi gas', 'fire hydrant',
                'sprinkler', 'pompa kebakaran', 'fm 200', 'alat pemadam',
                'lift', 'elevator', 'escalator', 'genset', 'generator',
            ],
            'Pekerjaan Elektrikal' => [
                'elektrikal', 'listrik', 'kabel', 'instalasi listrik', 'mcb',
                'panel listrik', 'lampu', 'lighting', 'led', 'saklar',
                'stop kontak', 'kotak saklar', 'sekring', 'kapasitor',
                'transformator', 'trafo', 'genset', 'ats', 'kapasitor bank',
                'grounding', 'penangkal petir', 'lightning protection',
                'cctv', 'fire alarm', 'bell', 'intercom', 'sound system',
                'data', 'network', 'fiber optic', 'structured cabling',
            ],
            'Pekerjaan Bongkar' => [
                'bongkar', 'demolition', 'pembongkaran', 'peruntuhan',
            ],
            'Pekerjaan Landscape' => [
                'landscape', 'taman', 'tanaman', 'rumput', 'pohon', 'paving',
                'perkerasan', 'jalan taman', 'pot tanaman', 'irrigasi taman',
                'gazebo', 'pergola', 'carport', 'pagar', 'gerbang',
            ],
            'Pekerjaan Mebelair' => [
                'mebelair', 'meubel', 'furniture', 'lemari', 'meja', 'kursi',
                'rak', 'kitchen set', 'backdrop', 'counter', 'display',
            ],
        ];

        $scores = [];
        foreach ($categories as $catName => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($desc, $kw)) {
                    // Longer keyword match = higher relevance
                    $score += strlen($kw);
                }
            }
            if ($score > 0) {
                $scores[$catName] = $score;
            }
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    /**
     * Check if a section code or description represents a Level 1 main section.
     */
    protected function isLevel1Section(string $text, ?string $kode): bool
    {
        $t = trim($text);
        $k = trim((string) $kode);

        // If code matches Roman numerals or single letters
        if ($k !== '') {
            if (preg_match('/^(?=[MDCLXVI])M*(C[MD]|D?C{0,3})(X[CL]|L?X{0,3})(I[XV]|V?I{0,3})\.?$/i', $k)) {
                return true;
            }
            if (preg_match('/^[A-Z]\.?$/i', $k)) {
                return true;
            }
        }

        // If code is empty, check description prefix
        if (preg_match('/^(?=[MDCLXVI])M*(C[MD]|D?C{0,3})(X[CL]|L?X{0,3})(I[XV]|V?I{0,3})\b/i', $t)) {
            return true;
        }
        if (preg_match('/^[A-Z]\b/i', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Identify all sheets that have a valid RAB structure.
     */
    protected function findValidSheets(array $sheets): array
    {
        $selected = $this->selectImportSheets($this->buildSheetCandidates($sheets));
        if ($selected !== []) {
            return array_map(function ($candidate) {
                unset($candidate['rows'], $candidate['qualityScore'], $candidate['sheetOrder']);

                return $candidate;
            }, $selected);
        }

        $validSheets = [];
        $bestScore = -1;
        $bestSheet = null;

        foreach ($sheets as $sheetName => $rows) {
            // Skip known non-RAB sheets
            if ($this->isNonRabSheet($sheetName, $rows, 0)) {
                continue;
            }

            // Find header row: look for row with uraian + volume + harga_satuan keywords
            $headerRowIndex = null;
            $headerKeywords = ['uraian', 'deskripsi', 'pekerjaan', 'description', 'item', 'nama barang', 'harga satuan', 'volume', 'satuan', 'harga', 'qty', 'jumlah', 'total', 'kode', 'no'];

            foreach ($rows as $idx => $row) {
                $lower = array_map(fn ($v) => strtolower(trim((string) $v)), $row);
                $matchCount = 0;
                $hasUraian = false;
                $hasVolume = false;
                $hasHarga = false;
                $hasNumeric = false;

                foreach ($lower as $cell) {
                    // Check if cell looks like a number (data row, not header)
                    if (is_numeric($cell) && (float) $cell > 0) {
                        $hasNumeric = true;
                    }

                    // Normalize whitespace: "j u m l a h" -> "jumlah"
                    $normalizedCell = preg_replace('/\s+/', '', $cell);

                    foreach ($headerKeywords as $kw) {
                        if (str_contains($cell, $kw) || str_contains($normalizedCell, $kw)) {
                            $matchCount++;
                            if (str_contains($cell, 'uraian') || str_contains($cell, 'deskripsi') || str_contains($cell, 'pekerjaan') || str_contains($cell, 'description') || str_contains($cell, 'item') || str_contains($cell, 'nama')) {
                                $hasUraian = true;
                            }
                            if (str_contains($cell, 'volume') || str_contains($cell, 'qty') || str_contains($cell, 'kuantitas') || str_contains($cell, 'koef') || str_contains($cell, 'perkiraan') || str_contains($cell, 'banyak')) {
                                $hasVolume = true;
                            }
                            if (str_contains($cell, 'harga satuan') || str_contains($cell, 'unit price') || str_contains($cell, 'hsp') || str_contains($cell, 'jumlah') || str_contains($cell, 'total')) {
                                $hasHarga = true;
                            }
                            break;
                        }
                    }
                }

                // Best header: has uraian + volume + harga_satuan keywords, NO numeric data
                if ($hasUraian && $hasVolume && $hasHarga && ! $hasNumeric) {
                    $headerRowIndex = $idx;
                    break;
                }
                // Good header: 3+ keywords, short cells, no numeric data
                if ($matchCount >= 3 && ! $hasNumeric) {
                    $shortCells = 0;
                    foreach ($row as $cell) {
                        if ($cell !== null && strlen(trim((string) $cell)) > 0 && strlen(trim((string) $cell)) < 30) {
                            $shortCells++;
                        }
                    }
                    if ($shortCells >= 3 && $headerRowIndex === null) {
                        $headerRowIndex = $idx;
                    }
                }
            }

            if ($headerRowIndex === null) {
                continue;
            }

            $colMap = $this->mapColumns($rows[$headerRowIndex]);
            if (! isset($colMap['uraian'])) {
                continue;
            }
            if (! isset($colMap['volume']) || (! isset($colMap['harga_satuan']) && ! isset($colMap['jumlah']))) {
                continue;
            }

            // Score this sheet
            $score = 0;
            $nameLower = strtolower($sheetName);
            if (str_contains($nameLower, 'rab')) {
                $score += 100;
            }
            if (str_contains($nameLower, 'impor')) {
                $score += 50;
            }
            if (str_contains($nameLower, 'rekap')) {
                $score -= 50;
            }
            if (str_contains($nameLower, 'analisa')) {
                $score -= 40;
            }

            $validSheets[] = [
                'sheetName' => $sheetName,
                'headerIndex' => $headerRowIndex,
                'colMap' => $colMap,
                'nameScore' => $score,
            ];
        }

        // Sort by nameScore descending (prefer RAB sheets)
        usort($validSheets, fn ($a, $b) => ($b['nameScore'] ?? 0) <=> ($a['nameScore'] ?? 0));

        return $validSheets;
    }

    protected function columnMapError(array $columnMap): ?string
    {
        if (! isset($columnMap['uraian'])) {
            return 'Kolom Uraian wajib ada.';
        }
        // Detail rows need either an explicit volume or a total (lump sum).
        if (! isset($columnMap['volume']) && ! isset($columnMap['jumlah'])) {
            return 'Kolom Volume atau Jumlah wajib ada.';
        }
        if (! isset($columnMap['harga_satuan']) && ! isset($columnMap['jumlah'])) {
            return 'Kolom Harga Satuan atau Jumlah wajib ada.';
        }

        return null;
    }
}
