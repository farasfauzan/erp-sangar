<?php

namespace Tests\Feature\Api;

use App\Models\GeneralLedger;
use App\Models\ChartOfAccount;

class FinancialReportControllerTest extends TestCase
{
    private function seedLedgerData(): void
    {
        // Create COA entries
        ChartOfAccount::firstOrCreate(['code' => '1101'], ['name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '4101'], ['name' => 'Pendapatan Jasa', 'type' => 'revenue', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '5101'], ['name' => 'Beban Gaji', 'type' => 'expense', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '3101'], ['name' => 'Modal', 'type' => 'equity', 'is_active' => true]);

        // Journal 1: Revenue recognition (debit kas, credit pendapatan)
        GeneralLedger::create([
            'journal_number' => 'JRN-20260101-0001', 'transaction_date' => '2026-01-15',
            'account_code' => '1101', 'debit' => 100000000, 'credit' => 0, 'description' => 'Pendapatan',
        ]);
        GeneralLedger::create([
            'journal_number' => 'JRN-20260101-0001', 'transaction_date' => '2026-01-15',
            'account_code' => '4101', 'debit' => 0, 'credit' => 100000000, 'description' => 'Pendapatan',
        ]);

        // Journal 2: Expense (debit beban, credit kas)
        GeneralLedger::create([
            'journal_number' => 'JRN-20260201-0001', 'transaction_date' => '2026-02-01',
            'account_code' => '5101', 'debit' => 30000000, 'credit' => 0, 'description' => 'Gaji',
        ]);
        GeneralLedger::create([
            'journal_number' => 'JRN-20260201-0001', 'transaction_date' => '2026-02-01',
            'account_code' => '1101', 'debit' => 0, 'credit' => 30000000, 'description' => 'Gaji',
        ]);
    }

    // ─── NERACA ───────────────────────────────────────────────────────────

    public function test_neraca_returns_balanced(): void
    {
        $this->actingAsRole('ACCOUNTING');
        $this->seedLedgerData();

        $this->getJson('/api/reports/neraca?date_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'as_of',
                    'aset' => ['items', 'total'],
                    'kewajiban' => ['items', 'total'],
                    'ekuitas' => ['items', 'laba_ditahan', 'total'],
                    'is_balanced',
                ],
            ]);
    }

    // ─── LABA RUGI ───────────────────────────────────────────────────────

    public function test_laba_rugi_returns_income_statement(): void
    {
        $this->actingAsRole('ACCOUNTING');
        $this->seedLedgerData();

        $this->getJson('/api/reports/laba-rugi?date_from=2026-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pendapatan.total', 100000000)
            ->assertJsonPath('data.beban.total', 30000000)
            ->assertJsonPath('data.laba_bersih', 70000000);
    }

    // ─── ARUS KAS ────────────────────────────────────────────────────────

    public function test_arus_kas_returns_cash_flow(): void
    {
        $this->actingAsRole('ACCOUNTING');
        $this->seedLedgerData();

        $this->getJson('/api/reports/arus-kas?date_from=2026-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'aktivitas_operasi',
                    'kas_masuk',
                    'kas_keluar',
                    'kas_bersih',
                ],
            ]);
    }

    // ─── ACCESS CONTROL ──────────────────────────────────────────────────

    public function test_lapangan_cannot_access_financial_reports(): void
    {
        $this->actingAsRole('LAPANGAN');

        $this->getJson('/api/reports/neraca')->assertForbidden();
        $this->getJson('/api/reports/laba-rugi')->assertForbidden();
        $this->getJson('/api/reports/arus-kas')->assertForbidden();
    }
}
