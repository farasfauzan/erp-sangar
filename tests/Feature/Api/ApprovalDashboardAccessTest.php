<?php

namespace Tests\Feature\Api;

class ApprovalDashboardAccessTest extends TestCase
{
    public function test_admin_can_load_every_approval_dashboard_data_source(): void
    {
        $this->actingAsRole('ADMIN');

        foreach (['/api/pos', '/api/spks', '/api/opnames', '/api/invoices', '/api/fund-requests'] as $endpoint) {
            $this->getJson($endpoint)->assertOk();
        }
    }
}
