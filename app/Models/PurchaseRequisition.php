<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisition extends Model
{
    use Auditable;

    protected $fillable = [
        'project_id', 'rab_budget_id', 'requested_by', 'pr_number',
        'item_name', 'unit', 'qty_requested', 'qty_approved',
        'status', 'notes', 'approved_by', 'approved_at',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function rabBudget()
    {
        return $this->belongsTo(RabBudget::class, 'rab_budget_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(User $user, float $qtyApproved = null): void
    {
        $this->update([
            'status'       => 'APPROVED',
            'qty_approved' => $qtyApproved ?? $this->qty_requested,
            'approved_by'  => $user->id,
            'approved_at'  => now(),
        ]);
    }

    public function reject(User $user, string $reason = null): void
    {
        $this->update([
            'status'      => 'REJECTED',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'notes'       => $reason,
        ]);
    }
}