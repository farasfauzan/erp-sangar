<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RabBudget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RabBudgetController extends Controller
{
    /**
     * Preview Excel (first 30 rows) - uses raw XML to avoid memory issues
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
        ]);

        $file = $request->file('file');
        $rows = [];
        $errors = [];
        $headerRowIndex = null;
        $colMap = [];

        try {
            $result = $this->parseXlsxRaw($file->getRealPath(), 30);
            $allRows = $result['rows'];
            $errors = $result['errors'];

            // Find header row containing 'uraian' (case-insensitive, partial match)
            foreach ($allRows as $idx => $row) {
                $lower = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                $hasUraian = false;
                foreach ($lower as $cell) {
                    if (str_contains($cell, 'uraian')) { $hasUraian = true; break; }
                }
                if ($hasUraian) {
                    $headerRowIndex = $idx;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header "Uraian" tidak ditemukan dalam 30 baris pertama.',
                    'debug_first_rows' => array_slice($allRows, 0, 5),
                ], 422);
            }

            // Map columns from header
            $headerRow = $allRows[$headerRowIndex];
            $colMap = $this->mapColumns($headerRow);

            if (!isset($colMap['uraian'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kolom "Uraian" tidak ditemukan pada header.',
                    'header_found' => $headerRow,
                ], 422);
            }

            // Parse data rows after header
            foreach ($allRows as $idx => $row) {
                if ($idx <= $headerRowIndex) continue;

                $uraian = trim((string)($row[$colMap['uraian']] ?? ''));
                if ($uraian === '' || strtolower($uraian) === 'jumlah' || strtolower($uraian) === 'total') continue;

                $volume = $this->parseNumber($row[$colMap['volume'] ?? -1] ?? null);
                $satuan = trim((string)($row[$colMap['satuan'] ?? -1] ?? ''));
                $harga = $this->parseNumber($row[$colMap['harga_satuan'] ?? -1] ?? null);
                $jumlah = $this->parseNumber($row[$colMap['jumlah'] ?? -1] ?? null);

                if ($volume && $harga && !$jumlah) {
                    $jumlah = $volume * $harga;
                }

                $rows[] = [
                    'no' => trim((string)($row[$colMap['no']] ?? '')),
                    'uraian' => $uraian,
                    'volume' => $volume,
                    'satuan' => $satuan,
                    'harga_satuan' => $harga,
                    'jumlah' => $jumlah,
                ];
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'headers' => ['No', 'Uraian Pekerjaan', 'Volume', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'],
                'rows' => $rows,
                'total_rows' => count($rows),
                'errors' => $errors,
                'column_mapping' => $colMap,
            ],
        ]);
    }

    /**
     * Auto-import: parse + insert in batches using raw XML
     */
    public function autoImport(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
        ]);

        $file = $request->file('file');
        $projectId = $request->project_id;
        $imported = 0;
        $errors = [];
        $batchSize = 200;
        $batch = [];
        $headerRowIndex = null;
        $colMap = [];

        try {
            $result = $this->parseXlsxRaw($file->getRealPath(), null); // null = all rows
            $allRows = $result['rows'];
            $errors = $result['errors'];

            // Find header (partial match)
            foreach ($allRows as $idx => $row) {
                $lower = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                $hasUraian = false;
                foreach ($lower as $cell) {
                    if (str_contains($cell, 'uraian')) { $hasUraian = true; break; }
                }
                if ($hasUraian) {
                    $headerRowIndex = $idx;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header "Uraian" tidak ditemukan.',
                ], 422);
            }

            $colMap = $this->mapColumns($allRows[$headerRowIndex]);

            if (!isset($colMap['uraian'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kolom "Uraian" tidak ditemukan.',
                ], 422);
            }

            DB::beginTransaction();

            // Clear existing RAB for this project
            RabBudget::where('project_id', $projectId)->delete();

            foreach ($allRows as $idx => $row) {
                if ($idx <= $headerRowIndex) continue;

                $uraian = trim((string)($row[$colMap['uraian']] ?? ''));
                if ($uraian === '' || strtolower($uraian) === 'jumlah' || strtolower($uraian) === 'total') continue;

                $volume = $this->parseNumber($row[$colMap['volume'] ?? -1] ?? null);
                $satuan = trim((string)($row[$colMap['satuan'] ?? -1] ?? ''));
                $harga = $this->parseNumber($row[$colMap['harga_satuan'] ?? -1] ?? null);
                $jumlah = $this->parseNumber($row[$colMap['jumlah'] ?? -1] ?? null);

                if ($volume && $harga && !$jumlah) {
                    $jumlah = $volume * $harga;
                }

                $batch[] = [
                    'project_id' => $projectId,
                    'code_item' => trim((string)($row[$colMap['kode'] ?? -1] ?? '')),
                    'description' => $uraian,
                    'volume' => $volume ?: 0,
                    'unit' => $satuan,
                    'unit_price' => $harga ?: 0,
                    'total_price' => $jumlah ?: 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    RabBudget::insert($batch);
                    $imported += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                RabBudget::insert($batch);
                $imported += count($batch);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RAB Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal import: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil import {$imported} item RAB.",
            'data' => [
                'imported' => $imported,
                'errors' => $errors,
                'project_id' => $projectId,
            ],
        ]);
    }

    /**
     * Raw XML parse of xlsx - memory efficient, no PhpSpreadsheet needed
     * xlsx is a zip: xl/sharedStrings.xml (string table) + xl/worksheets/sheet1.xml (data)
     */
    private function parseXlsxRaw(string $path, ?int $maxRows): array
    {
        $rows = [];
        $errors = [];
        $sharedStrings = [];

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open xlsx file');
        }

        // 1. Read shared strings
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = simplexml_load_string($ssXml);
            if ($xml) {
                $ns = $xml->getNamespaces(true);
                foreach ($xml->si as $si) {
                    // Handle rich text (multiple <t> elements)
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } else {
                        // Rich text: concatenate all <r><t>...</t></r>
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // 2. Read sheet data
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new \RuntimeException('No sheet1.xml found');
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml) {
            $zip->close();
            throw new \RuntimeException('Cannot parse sheet XML');
        }

        $rowCount = 0;
        foreach ($xml->sheetData->row as $rowNode) {
            if ($maxRows !== null && $rowCount >= $maxRows) break;

            $rowData = [];
            $maxCol = 0;

            foreach ($rowNode->c as $cell) {
                $ref = (string)$cell['r']; // e.g. "A1", "B5"
                $colIndex = $this->columnLetterToIndex(preg_replace('/\d+/', '', $ref));

                $type = (string)$cell['t'];
                $value = null;

                if ($type === 's') {
                    // Shared string reference
                    $si = (int)$cell->v;
                    $value = $sharedStrings[$si] ?? '';
                } elseif (isset($cell->v)) {
                    $value = (string)$cell->v;
                } elseif (isset($cell->is->t)) {
                    // Inline string
                    $value = (string)$cell->is->t;
                }

                // Ensure array is large enough
                while (count($rowData) <= $colIndex) {
                    $rowData[] = '';
                }
                $rowData[$colIndex] = $value;
                $maxCol = max($maxCol, $colIndex);
            }

            $rows[] = $rowData;
            $rowCount++;
        }

        $zip->close();
        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Convert column letter (A, B, ..., Z, AA, AB, ...) to 0-based index
     */
    private function columnLetterToIndex(string $letter): int
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
     * Map header row to known column names
     */
    private function mapColumns(array $headerRow): array
    {
        $map = [];
        $knownCols = [
            'no' => ['no', 'nomor', 'no.', 'no urut'],
            'kode' => ['kode', 'kode pekerjaan', 'kode rekening', 'item'],
            'uraian' => ['uraian', 'uraian pekerjaan', 'deskripsi', 'description', 'pekerjaan'],
            'volume' => ['volume', 'vol', 'qty', 'quantity'],
            'satuan' => ['satuan', 'unit', 'uom'],
            'harga_satuan' => ['harga satuan', 'harga_satuan', 'hs', 'harga', 'unit price', 'harga per satuan'],
            'jumlah' => ['jumlah', 'total', 'jml', 'amount', 'total harga'],
        ];

        foreach ($headerRow as $colIdx => $header) {
            $h = strtolower(trim((string)$header));
            foreach ($knownCols as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($h === $alias || str_contains($h, $alias)) {
                        $map[$key] = $colIdx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Parse number from various formats (Indonesian: 1.000.000,00 or English: 1,000,000.00)
     */
    private function parseNumber($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float)$value;

        $s = trim((string)$value);
        $s = str_replace(['Rp', 'rp', 'RP', ' '], '', $s);

        // Indonesian format: 1.000.000,50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return is_numeric($s) ? (float)$s : null;
        }

        // English format: 1,000,000.50
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);
            return is_numeric($s) ? (float)$s : null;
        }

        // Plain number with comma decimal
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : null;
    }

    public function index(Request $request)
    {
        $query = RabBudget::with('project');
        $projectId = $request->route('projectId') ?? $request->get('project_id');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('id')->paginate($request->get('per_page', 50)),
        ]);
    }

    public function show($id)
    {
        $rab = RabBudget::with('project')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $rab]);
    }

    public function update(Request $request, $id)
    {
        $rab = RabBudget::findOrFail($id);

        if ($rab->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'RAB item terkunci (APPROVED). Tidak bisa diedit.',
            ], 403);
        }

        $data = $request->only(['code_item', 'description', 'unit', 'volume', 'unit_price', 'total_price', 'category']);
        foreach (['kode' => 'code_item', 'uraian' => 'description', 'satuan' => 'unit', 'harga_satuan' => 'unit_price', 'jumlah' => 'total_price'] as $old => $new) {
            if ($request->has($old)) {
                $data[$new] = $request->input($old);
            }
        }
        $rab->update($data);
        return response()->json(['success' => true, 'data' => $rab]);
    }

    public function destroy($id)
    {
        $rab = RabBudget::findOrFail($id);

        if ($rab->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'RAB item terkunci (APPROVED). Tidak bisa dihapus.',
            ], 403);
        }

        $rab->delete();
        return response()->json(['success' => true, 'message' => 'RAB item deleted']);
    }

    /**
     * Submit RAB for approval (bulk: all DRAFT items for a project)
     */
    public function submitForApproval(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:projects,id']);
        $count = RabBudget::submitForApproval($request->project_id);
        return response()->json([
            'success' => true,
            'message' => "{$count} item RAB diajukan untuk approval.",
            'data' => ['updated' => $count],
        ]);
    }

    /**
     * Approve all pending RAB items for a project
     */
    public function approve(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:projects,id']);
        $count = RabBudget::approveAll($request->project_id, Auth::user());
        return response()->json([
            'success' => true,
            'message' => "{$count} item RAB disetujui.",
            'data' => ['approved' => $count],
        ]);
    }

    /**
     * Reject all pending RAB items for a project
     */
    public function reject(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:projects,id']);
        $count = RabBudget::rejectAll($request->project_id, Auth::user());
        return response()->json([
            'success' => true,
            'message' => "{$count} item RAB ditolak.",
            'data' => ['rejected' => $count],
        ]);
    }

    /**
     * Roll-up summary by category
     */
    public function rollUp(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:projects,id']);
        $projectId = $request->project_id;
        return response()->json([
            'success' => true,
            'data' => [
                'rollup' => RabBudget::rollUp($projectId),
                'total_budget' => RabBudget::totalBudget($projectId),
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $projectId = $request->get('project_id');
        $query = RabBudget::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $totalBudget = $query->sum('total_price');
        $totalItems = $query->count();
        $byStatus = RabBudget::select(DB::raw("COALESCE(category, 'Umum') as status"), DB::raw('count(*) as count'), DB::raw('sum(total_price) as total'))
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->groupBy('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_budget' => $totalBudget,
                'total_items' => $totalItems,
                'by_status' => $byStatus,
            ],
        ]);
    }
}