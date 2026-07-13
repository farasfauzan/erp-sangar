<?php

namespace Tests\Feature\Api;

use App\Models\Efaktur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EfakturControllerTest extends TestCase
{
    // ─── INDEX ────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_efaktur(): void
    {
        $this->actingAsRole('PAJAK');
        Efaktur::factory()->count(3)->create();

        $this->getJson('/api/efaktur')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['data', 'current_page', 'total'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_index_filter_by_status(): void
    {
        $this->actingAsRole('ADMIN');
        Efaktur::factory()->create(['status' => 'draft']);
        Efaktur::factory()->create(['status' => 'approved']);

        $this->getJson('/api/efaktur?status=draft')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────

    public function test_show_returns_efaktur(): void
    {
        $this->actingAsRole('ACCOUNTING');
        $efaktur = Efaktur::factory()->create();

        $this->getJson("/api/efaktur/{$efaktur->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.faktur_number', $efaktur->faktur_number);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $this->actingAsRole('ADMIN');

        $this->getJson('/api/efaktur/99999')->assertNotFound();
    }

    // ─── UPLOAD CSV ───────────────────────────────────────────────────────

    public function test_upload_csv_imports_records(): void
    {
        $this->actingAsRole('PAJAK');
        Storage::fake('local');

        $csv = "faktur_date,faktur_number,npwp_penjual,nama_penjual,npwp_pembeli,nama_pembeli,dpp,ppn,ppnbm\n";
        $csv .= "2026-01-15,010.000-123456.2026,01.234.567.8-901.000,PT Sumber Rejeki,11.234.567.8-901.000,PT Pembeli Jaya,100000000,11000000,0\n";
        $csv .= "2026-02-20,010.000-789012.2026,01.234.567.8-901.000,PT Sumber Rejeki,,,50000000,,0\n";

        $file = UploadedFile::fake()->createWithContent('efaktur.csv', $csv);

        $this->postJson('/api/efaktur/upload', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported', 2);
    }

    public function test_upload_csv_reports_validation_errors(): void
    {
        $this->actingAsRole('PAJAK');

        $csv = "faktur_date,faktur_number,npwp_penjual,nama_penjual,dpp\n";
        $csv .= "2026-01-15,,01.234.567.8-901.000,PT Sumber Rejeki,100000\n"; // missing faktur_number

        $file = UploadedFile::fake()->createWithContent('efaktur.csv', $csv);

        $this->postJson('/api/efaktur/upload', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('imported', 0);
    }

    // ─── VALIDATE ─────────────────────────────────────────────────────────

    public function test_validate_draft_efaktur_succeeds(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create([
            'status' => 'draft',
            'dpp' => 100000000,
            'ppn' => 11000000,
        ]);

        $this->putJson("/api/efaktur/{$efaktur->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated');
    }

    public function test_validate_non_draft_fails(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create(['status' => 'approved']);

        $this->putJson("/api/efaktur/{$efaktur->id}/validate")
            ->assertStatus(422);
    }

    // ─── STATUS TRANSITIONS ───────────────────────────────────────────────

    public function test_submit_validated_efaktur(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create(['status' => 'validated']);

        $this->putJson("/api/efaktur/{$efaktur->id}/status", ['status' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_approve_submitted_efaktur(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create(['status' => 'submitted']);

        $this->putJson("/api/efaktur/{$efaktur->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create(['status' => 'draft']);

        $this->putJson("/api/efaktur/{$efaktur->id}/status", ['status' => 'approved'])
            ->assertStatus(422);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes(): void
    {
        $this->actingAsRole('PAJAK');
        $efaktur = Efaktur::factory()->create();

        $this->deleteJson("/api/efaktur/{$efaktur->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
