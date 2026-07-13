<?php

namespace Tests\Feature\Api;

use App\Models\Tax;

class RestitusiControllerTest extends TestCase
{
    // ─── SUBMIT RESTITUSI ──────────────────────────────────────────────────

    public function test_submit_restitusi_succeeds(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'none']);

        $this->postJson("/api/taxes/{$tax->id}/restitusi", [
            'restitusi_amount' => 5000000,
            'restitusi_notes' => 'Restitusi PPN Q1 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.restitusi_status', 'pending')
            ->assertJsonPath('data.restitusi_amount', '5000000.00');
    }

    public function test_submit_restitusi_already_pending_fails(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'pending', 'restitusi_amount' => 1000000]);

        $this->postJson("/api/taxes/{$tax->id}/restitusi", [
            'restitusi_amount' => 2000000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── APPROVE RESTITUSI ────────────────────────────────────────────────

    public function test_approve_restitusi_succeeds(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'pending', 'restitusi_amount' => 5000000]);

        $this->putJson("/api/taxes/{$tax->id}/restitusi/approve", [
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('data.restitusi_status', 'approved');
    }

    public function test_reject_restitusi_succeeds(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'pending', 'restitusi_amount' => 5000000]);

        $this->putJson("/api/taxes/{$tax->id}/restitusi/approve", [
            'action' => 'reject',
            'restitusi_notes' => 'Dokumen tidak lengkap',
        ])
            ->assertOk()
            ->assertJsonPath('data.restitusi_status', 'rejected');
    }

    public function test_approve_non_pending_fails(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'none']);

        $this->putJson("/api/taxes/{$tax->id}/restitusi/approve", [
            'action' => 'approve',
        ])
            ->assertStatus(422);
    }

    // ─── PAY RESTITUSI ────────────────────────────────────────────────────

    public function test_pay_approved_restitusi_succeeds(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'approved', 'restitusi_amount' => 5000000]);

        $this->putJson("/api/taxes/{$tax->id}/restitusi/pay")
            ->assertOk()
            ->assertJsonPath('data.restitusi_status', 'paid');
    }

    public function test_pay_non_approved_fails(): void
    {
        $this->actingAsRole('PAJAK');
        $tax = Tax::factory()->create(['restitusi_status' => 'pending']);

        $this->putJson("/api/taxes/{$tax->id}/restitusi/pay")
            ->assertStatus(422);
    }
}
