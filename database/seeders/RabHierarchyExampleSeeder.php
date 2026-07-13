<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RabHierarchyExampleSeeder extends Seeder
{
    public function run(): void
    {
        $projectId = 1; // Adjust to your project

        // Clear existing RAB for this project
        DB::table('rab_budgets')->where('project_id', $projectId)->delete();

        $now = now();

        // ═══════════════════════════════════════════════════════════
        // HIERARKI RAB - CONTOH STRUKTUR KODE
        // ═══════════════════════════════════════════════════════════
        //
        // Format Kode: [PREFIX].[NO].[SUB-NO]
        //
        // PREFIX:
        //   M  = Material (Bahan Bangunan)
        //   T  = Tenaga Kerja (Upah Tukang)
        //   A  = Alat (Peralatan/Mesin)
        //   S  = Subkon (Subkontraktor)
        //   P  = Pekerjaan (Section Utama)
        //
        // Contoh:
        //   P.01       = Pekerjaan Persiapan (section)
        //   P.01.01    = Sub-item pekerjaan
        //   M.01       = Material: Semen Portland
        //   M.01.01    = Material: Semen Portland 50kg
        //   T.01       = Tenaga: Tukang Batu
        //   T.01.01    = Tenaga: Tukang Batu Level 1
        //   A.01       = Alat: Molen Beton
        //   S.01       = Subkon: Pekerjaan Struktur
        // ═══════════════════════════════════════════════════════════

        $items = [
            // ── PEKERJAAN PERSIAPAN ──────────────────────────────
            ['code' => 'P.01', 'desc' => 'Pekerjaan Persiapan', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Pekerjaan Persiapan', 'parent' => null],
            ['code' => 'P.01.01', 'desc' => 'Pembersihan Lahan', 'unit' => 'm²', 'vol' => 500, 'price' => 15000, 'cat' => 'Pekerjaan Persiapan', 'parent' => 'P.01'],
            ['code' => 'P.01.02', 'desc' => 'Pemagaran Situs', 'unit' => 'm\'', 'vol' => 200, 'price' => 85000, 'cat' => 'Pekerjaan Persiapan', 'parent' => 'P.01'],
            ['code' => 'P.01.03', 'desc' => 'Mobilisasi Alat', 'unit' => 'ls', 'vol' => 1, 'price' => 25000000, 'cat' => 'Pekerjaan Persiapan', 'parent' => 'P.01'],

            // ── PEKERJAAN TANAH ──────────────────────────────────
            ['code' => 'P.02', 'desc' => 'Pekerjaan Tanah', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Pekerjaan Tanah', 'parent' => null],
            ['code' => 'P.02.01', 'desc' => 'Galian Tanah Biasa', 'unit' => 'm³', 'vol' => 300, 'price' => 75000, 'cat' => 'Pekerjaan Tanah', 'parent' => 'P.02'],
            ['code' => 'P.02.02', 'desc' => 'Urugan Tanah Kembali', 'unit' => 'm³', 'vol' => 150, 'price' => 45000, 'cat' => 'Pekerjaan Tanah', 'parent' => 'P.02'],
            ['code' => 'P.02.03', 'desc' => 'Pemadatan Tanah', 'unit' => 'm³', 'vol' => 200, 'price' => 35000, 'cat' => 'Pekerjaan Tanah', 'parent' => 'P.02'],

            // ── MATERIAL (BAHAN BANGUNAN) ────────────────────────
            ['code' => 'M.01', 'desc' => 'Semen Portland', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.01.01', 'desc' => 'Semen Portland 50kg', 'unit' => 'Zak', 'vol' => 500, 'price' => 65000, 'cat' => 'Material', 'parent' => 'M.01'],
            ['code' => 'M.01.02', 'desc' => 'Semen Putih 40kg', 'unit' => 'Zak', 'vol' => 50, 'price' => 125000, 'cat' => 'Material', 'parent' => 'M.01'],

            ['code' => 'M.02', 'desc' => 'Pasir', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.02.01', 'desc' => 'Pasir Beton', 'unit' => 'm³', 'vol' => 200, 'price' => 350000, 'cat' => 'Material', 'parent' => 'M.02'],
            ['code' => 'M.02.02', 'desc' => 'Pasir Pasang', 'unit' => 'm³', 'vol' => 100, 'price' => 320000, 'cat' => 'Material', 'parent' => 'M.02'],
            ['code' => 'M.02.03', 'desc' => 'Pasir Urug', 'unit' => 'm³', 'vol' => 150, 'price' => 280000, 'cat' => 'Material', 'parent' => 'M.02'],

            ['code' => 'M.03', 'desc' => 'Batu', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.03.01', 'desc' => 'Batu Belah 10-15cm', 'unit' => 'm³', 'vol' => 100, 'price' => 420000, 'cat' => 'Material', 'parent' => 'M.03'],
            ['code' => 'M.03.02', 'desc' => 'Batu Split', 'unit' => 'm³', 'vol' => 80, 'price' => 380000, 'cat' => 'Material', 'parent' => 'M.03'],

            ['code' => 'M.04', 'desc' => 'Beton Ready Mix', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.04.01', 'desc' => 'Beton K-225', 'unit' => 'm³', 'vol' => 150, 'price' => 850000, 'cat' => 'Material', 'parent' => 'M.04'],
            ['code' => 'M.04.02', 'desc' => 'Beton K-300', 'unit' => 'm³', 'vol' => 80, 'price' => 950000, 'cat' => 'Material', 'parent' => 'M.04'],

            ['code' => 'M.05', 'desc' => 'Besi Tulangan', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.05.01', 'desc' => 'Besi Tulangan Ø10', 'unit' => 'Batang', 'vol' => 500, 'price' => 95000, 'cat' => 'Material', 'parent' => 'M.05'],
            ['code' => 'M.05.02', 'desc' => 'Besi Tulangan Ø12', 'unit' => 'Batang', 'vol' => 400, 'price' => 135000, 'cat' => 'Material', 'parent' => 'M.05'],
            ['code' => 'M.05.03', 'desc' => 'Besi Tulangan Ø16', 'unit' => 'Batang', 'vol' => 300, 'price' => 245000, 'cat' => 'Material', 'parent' => 'M.05'],

            ['code' => 'M.06', 'desc' => 'Kayu', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.06.01', 'desc' => 'Kayu Bekisting 3mm', 'unit' => 'Lembar', 'vol' => 200, 'price' => 85000, 'cat' => 'Material', 'parent' => 'M.06'],
            ['code' => 'M.06.02', 'desc' => 'Kayu Balok 5/7', 'unit' => 'Batang', 'vol' => 150, 'price' => 125000, 'cat' => 'Material', 'parent' => 'M.06'],

            ['code' => 'M.07', 'desc' => 'Bata & Plester', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Material', 'parent' => null],
            ['code' => 'M.07.01', 'desc' => 'Bata Merah', 'unit' => 'Buah', 'vol' => 10000, 'price' => 1200, 'cat' => 'Material', 'parent' => 'M.07'],
            ['code' => 'M.07.02', 'desc' => 'Mortar Plester', 'unit' => 'Zak', 'vol' => 100, 'price' => 55000, 'cat' => 'Material', 'parent' => 'M.07'],

            // ── TENAGA KERJA (UPAH) ──────────────────────────────
            ['code' => 'T.01', 'desc' => 'Tenaga Kerja Umum', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Upah', 'parent' => null],
            ['code' => 'T.01.01', 'desc' => 'Pekerja Umum (Helper)', 'unit' => 'OH', 'vol' => 500, 'price' => 150000, 'cat' => 'Upah', 'parent' => 'T.01'],
            ['code' => 'T.01.02', 'desc' => 'Mandor', 'unit' => 'OH', 'vol' => 100, 'price' => 250000, 'cat' => 'Upah', 'parent' => 'T.01'],

            ['code' => 'T.02', 'desc' => 'Tukang Spesialis', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Upah', 'parent' => null],
            ['code' => 'T.02.01', 'desc' => 'Tukang Batu', 'unit' => 'OH', 'vol' => 300, 'price' => 200000, 'cat' => 'Upah', 'parent' => 'T.02'],
            ['code' => 'T.02.02', 'desc' => 'Tukang Kayu', 'unit' => 'OH', 'vol' => 200, 'price' => 200000, 'cat' => 'Upah', 'parent' => 'T.02'],
            ['code' => 'T.02.03', 'desc' => 'Tukang Besi', 'unit' => 'OH', 'vol' => 200, 'price' => 200000, 'cat' => 'Upah', 'parent' => 'T.02'],
            ['code' => 'T.02.04', 'desc' => 'Tukang Cat', 'unit' => 'OH', 'vol' => 100, 'price' => 180000, 'cat' => 'Upah', 'parent' => 'T.02'],
            ['code' => 'T.02.05', 'desc' => 'Tukang Listrik', 'unit' => 'OH', 'vol' => 50, 'price' => 250000, 'cat' => 'Upah', 'parent' => 'T.02'],

            // ── ALAT (PERALATAN) ─────────────────────────────────
            ['code' => 'A.01', 'desc' => 'Alat Berat', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Alat', 'parent' => null],
            ['code' => 'A.01.01', 'desc' => 'Excavator PC200', 'unit' => 'Jam', 'vol' => 100, 'price' => 350000, 'cat' => 'Alat', 'parent' => 'A.01'],
            ['code' => 'A.01.02', 'desc' => 'Bulldozer D6', 'unit' => 'Jam', 'vol' => 50, 'price' => 450000, 'cat' => 'Alat', 'parent' => 'A.01'],
            ['code' => 'A.01.03', 'desc' => 'Crane 25 Ton', 'unit' => 'Jam', 'vol' => 30, 'price' => 500000, 'cat' => 'Alat', 'parent' => 'A.01'],

            ['code' => 'A.02', 'desc' => 'Alat Ringan', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Alat', 'parent' => null],
            ['code' => 'A.02.01', 'desc' => 'Molen Beton 350L', 'unit' => 'Hari', 'vol' => 60, 'price' => 175000, 'cat' => 'Alat', 'parent' => 'A.02'],
            ['code' => 'A.02.02', 'desc' => 'Vibrator Beton', 'unit' => 'Hari', 'vol' => 60, 'price' => 125000, 'cat' => 'Alat', 'parent' => 'A.02'],
            ['code' => 'A.02.03', 'desc' => 'Concrete Pump', 'unit' => 'Hari', 'vol' => 30, 'price' => 3500000, 'cat' => 'Alat', 'parent' => 'A.02'],

            // ── SUBKON (SUBKONTRAKTOR) ───────────────────────────
            ['code' => 'S.01', 'desc' => 'Subkon Struktur', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Subkon', 'parent' => null],
            ['code' => 'S.01.01', 'desc' => 'Subkon Pekerjaan Pondasi', 'unit' => 'ls', 'vol' => 1, 'price' => 150000000, 'cat' => 'Subkon', 'parent' => 'S.01'],
            ['code' => 'S.01.02', 'desc' => 'Subkon Pekerjaan Beton', 'unit' => 'ls', 'vol' => 1, 'price' => 200000000, 'cat' => 'Subkon', 'parent' => 'S.01'],

            ['code' => 'S.02', 'desc' => 'Subkon MEP', 'unit' => '', 'vol' => 1, 'price' => 0, 'cat' => 'Subkon', 'parent' => null],
            ['code' => 'S.02.01', 'desc' => 'Subkon Instalasi Listrik', 'unit' => 'ls', 'vol' => 1, 'price' => 85000000, 'cat' => 'Subkon', 'parent' => 'S.02'],
            ['code' => 'S.02.02', 'desc' => 'Subkon Instalasi Plumbing', 'unit' => 'ls', 'vol' => 1, 'price' => 65000000, 'cat' => 'Subkon', 'parent' => 'S.02'],
            ['code' => 'S.02.03', 'desc' => 'Subkon AC & Ventilasi', 'unit' => 'ls', 'vol' => 1, 'price' => 45000000, 'cat' => 'Subkon', 'parent' => 'S.02'],
        ];

        // Build parent ID map
        $parentMap = [];
        foreach ($items as $item) {
            if ($item['parent'] === null) {
                $id = DB::table('rab_budgets')->insertGetId([
                    'project_id' => $projectId,
                    'code_item' => $item['code'],
                    'description' => $item['desc'],
                    'unit' => $item['unit'],
                    'volume' => $item['vol'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['vol'] * $item['price'],
                    'category' => $item['cat'],
                    'status' => 'DRAFT',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $parentMap[$item['code']] = $id;
            }
        }

        // Insert children with parent_id
        foreach ($items as $item) {
            if ($item['parent'] !== null) {
                DB::table('rab_budgets')->insert([
                    'project_id' => $projectId,
                    'code_item' => $item['code'],
                    'description' => $item['desc'],
                    'unit' => $item['unit'],
                    'volume' => $item['vol'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['vol'] * $item['price'],
                    'category' => $item['cat'],
                    'parent_id' => $parentMap[$item['parent']],
                    'status' => 'DRAFT',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command->info('✅ RAB Hierarchy Example seeded: ' . count($items) . ' items for project #' . $projectId);
        $this->command->info('   P (Pekerjaan) : ' . count(array_filter($items, fn($i) => str_starts_with($i['code'], 'P.'))) . ' items');
        $this->command->info('   M (Material)  : ' . count(array_filter($items, fn($i) => str_starts_with($i['code'], 'M.'))) . ' items');
        $this->command->info('   T (Tenaga)    : ' . count(array_filter($items, fn($i) => str_starts_with($i['code'], 'T.'))) . ' items');
        $this->command->info('   A (Alat)      : ' . count(array_filter($items, fn($i) => str_starts_with($i['code'], 'A.'))) . ' items');
        $this->command->info('   S (Subkon)    : ' . count(array_filter($items, fn($i) => str_starts_with($i['code'], 'S.'))) . ' items');
    }
}
