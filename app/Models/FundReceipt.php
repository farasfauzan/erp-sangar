<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'fund_request_id', 'receipt_number', 'amount',
        'status', 'received_by', 'received_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function fundRequest(): BelongsTo
    {
        return $this->belongsTo(FundRequest::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
