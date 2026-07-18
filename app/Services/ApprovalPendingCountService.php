<?php

namespace App\Services;

use App\Models\FundRequest;
use App\Models\Invoice;
use App\Models\Opname;
use App\Models\PurchaseOrder;
use App\Models\RabBudget;
use App\Models\Spk;
use App\Models\User;

class ApprovalPendingCountService
{
    /**
     * Return actionable approval totals for the signed-in user's role.
     * These counts intentionally come from document statuses, not unread
     * notifications, so reading the bell does not hide unfinished work.
     */
    public function forUser(User $user): array
    {
        $rab = RabBudget::query()
            ->where('status', RabBudget::STATUS_PENDING)
            ->whereRaw('version = (SELECT MAX(latest.version) FROM rab_budgets AS latest WHERE latest.project_id = rab_budgets.project_id AND latest.deleted_at IS NULL)')
            ->count();
        $projectPo = PurchaseOrder::query()->where('po_level', 'PROJECT')->where('status', 'DRAFT')->count();
        $supplierPo = PurchaseOrder::query()->where('po_level', 'SUPPLIER')->where('status', 'PENDING_APPROVAL')->count();
        $spk = Spk::query()->where('status', 'PENDING_APPROVAL')->count();
        $opname = Opname::query()->where('status', 'PENDING')->count();
        $invoiceEngineer = Invoice::query()->where('status', 'PENDING_ENGINEER')->count();
        $invoiceFinance = Invoice::query()->whereIn('status', ['ENGINEER_VERIFIED', 'PENDING_CASHFLOW'])->count();
        $invoiceManager = Invoice::query()->where('status', 'PENDING_APPROVAL')->count();
        $fundFinance = FundRequest::query()->whereIn('status', ['PENDING_VERIFICATION', 'LPJ_SUBMITTED'])->count();
        $fundManager = FundRequest::query()->whereIn('status', ['PENDING_APPROVAL', 'LPJ_PENDING_APPROVAL'])->count();

        $role = (string) $user->role?->role_name;

        return match ($role) {
            'ENGINEER' => $this->counts($rab + $opname, $projectPo, $invoiceEngineer),
            'VERIFIKATOR_KEU' => $this->counts($invoiceFinance + $fundFinance),
            'MGR_KOMERSIAL' => $this->counts($supplierPo + $spk + $invoiceManager + $fundManager),
            'ADMIN' => $this->counts(
                $rab + $supplierPo + $spk + $opname + $invoiceFinance + $invoiceManager + $fundFinance + $fundManager,
                $projectPo,
                $invoiceEngineer,
            ),
            default => $this->counts(0),
        };
    }

    private function counts(int $main, int $needs = 0, int $invoices = 0): array
    {
        return [
            'all' => $main + $needs + $invoices,
            'main' => $main,
            'needs' => $needs,
            'invoices' => $invoices,
        ];
    }
}
