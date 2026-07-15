<?php

namespace Tests\Unit;

use App\Traits\HandlesRabParsing;
use PHPUnit\Framework\TestCase;

class HandlesRabParsingTest extends TestCase
{
    private function parser(): object
    {
        return new class
        {
            use HandlesRabParsing;

            public function validSheets(array $sheets): array
            {
                return $this->findValidSheets($sheets);
            }

            public function isRecapSection(array $row, array $columnMap, string $description): bool
            {
                return $this->isNumberedRecapSectionRow($row, $columnMap, $description);
            }

            public function isRecapItem(array $row, array $columnMap): bool
            {
                return $this->isNumberedRecapItemRow($row, $columnMap);
            }
        };
    }

    public function test_it_follows_a_merged_uraian_header_to_the_data_bearing_column(): void
    {
        $sheets = [
            'SECTION 1' => [
                ['PEKERJAAN', ':', 'Contoh Proyek'],
                ['No.', 'Uraian', '', '', 'Analis', 'Satuan', 'Perkiraan Kuantitas', 'Harga Satuan', 'Jumlah Harga Total'],
                ['a', 'b', '', '', 'c', 'c', 'd', 'e', 'f = (d x e)'],
                ['', '1', '', 'Galian Tanah', 'T.01', 'm3', '10', '100000', '1000000'],
                ['', '2', '', 'Timbunan', 'T.02', 'm3', '5', '200000', '1000000'],
            ],
        ];

        $valid = $this->parser()->validSheets($sheets);

        $this->assertCount(1, $valid);
        $this->assertSame(2, $valid[0]['headerIndex']);
        $this->assertSame(3, $valid[0]['colMap']['uraian']);
        $this->assertSame(6, $valid[0]['colMap']['volume']);
        $this->assertSame(7, $valid[0]['colMap']['harga_satuan']);
        $this->assertSame(8, $valid[0]['colMap']['jumlah']);
    }

    public function test_it_prefers_canonical_rab_sheets_over_derivative_sheets(): void
    {
        $rowSet = fn (string $description) => [
            ['No', 'Uraian Pekerjaan', 'Satuan', 'Volume', 'Harga Satuan', 'Jumlah'],
            ['1', $description, 'm2', '10', '250000', '2500000'],
        ];

        $valid = $this->parser()->validSheets([
            'TKDN' => $rowSet('Salinan TKDN'),
            'RAB' => $rowSet('Pekerjaan Arsitektur'),
            'RAB MEP' => $rowSet('Pekerjaan Elektrikal'),
            'IMPOR' => $rowSet('Barang Impor'),
        ]);

        $this->assertSame(['RAB', 'RAB MEP'], array_column($valid, 'sheetName'));
    }

    public function test_it_keeps_multiple_section_sheets_when_no_canonical_rab_exists(): void
    {
        $rowSet = fn (string $description) => [
            ['No', 'Uraian', 'Kode', 'Satuan', 'Volume', 'Harga Satuan', 'Jumlah'],
            ['1', $description, 'A.1', 'm3', '10', '100000', '1000000'],
        ];

        $valid = $this->parser()->validSheets([
            'TKDN' => $rowSet('Salinan TKDN'),
            'PERSIAPAN' => $rowSet('Mobilisasi'),
            'SECTION 1' => $rowSet('Galian Tanah'),
            'SECTION 2' => $rowSet('Timbunan'),
        ]);

        $this->assertSame(['PERSIAPAN', 'SECTION 1', 'SECTION 2'], array_column($valid, 'sheetName'));
    }

    public function test_it_uses_a_detailed_recap_when_no_detail_sections_exist(): void
    {
        $recap = [['No', 'Uraian Pekerjaan', 'Volume', 'Satuan', 'Harga Satuan', 'Jumlah Harga']];
        for ($index = 1; $index <= 25; $index++) {
            $recap[] = [(string) $index, "Item {$index}", '1', 'ls', '100000', '100000'];
        }

        $valid = $this->parser()->validSheets([
            'REKAPITULASI PEKERJAAN KONSTRUKSI' => $recap,
            'TKDN' => $recap,
        ]);

        $this->assertSame(['REKAPITULASI PEKERJAAN KONSTRUKSI'], array_column($valid, 'sheetName'));
    }

    public function test_it_uses_numbered_parent_items_from_a_hierarchical_recap(): void
    {
        $recap = [['No', 'Uraian Pekerjaan / Rincian Sumber Daya', 'Volume', 'Satuan', 'Harga Satuan', 'Jumlah Harga']];
        for ($index = 1; $index <= 10; $index++) {
            $recap[] = [(string) $index, "Pekerjaan {$index}", '1', 'ls', '100000', '100000'];
            $recap[] = ['', "- Material {$index}", '2', 'kg', '25000', '50000'];
        }

        $section = [
            ['No', 'Uraian Pekerjaan', 'Volume', 'Satuan', 'Harga Satuan', 'Jumlah Harga'],
            ['1', 'Versi Ringkas', '1', 'ls', '100000', '100000'],
        ];

        $valid = $this->parser()->validSheets([
            'REKAPITULASI PEKERJAAN KONSTRUKSI' => $recap,
            'PEKERJAAN PERSIAPAN' => $section,
            'PEKERJAAN STRUKTUR' => $section,
        ]);

        $this->assertSame(['REKAPITULASI PEKERJAAN KONSTRUKSI'], array_column($valid, 'sheetName'));
        $this->assertTrue($valid[0]['colMap']['_require_numbered_item']);
    }

    public function test_it_treats_roman_numeral_recap_rows_with_subtotals_as_category_boundaries(): void
    {
        $columnMap = [
            'no' => 0,
            'uraian' => 1,
            'jumlah' => 5,
            '_require_numbered_item' => true,
        ];

        $this->assertTrue($this->parser()->isRecapSection(
            ['II', 'PEKERJAAN TANAH & PONDASI', null, null, null, 380000000],
            $columnMap,
            'PEKERJAAN TANAH & PONDASI'
        ));
        $this->assertFalse($this->parser()->isRecapSection(
            ['2', 'Urugan Pasir', 1, 'LS', 15000000, 15000000],
            $columnMap,
            'Urugan Pasir'
        ));
        $this->assertTrue($this->parser()->isRecapItem(
            ['2', 'Urugan Pasir', 1, 'LS', 15000000, 15000000],
            $columnMap
        ));
    }
}
