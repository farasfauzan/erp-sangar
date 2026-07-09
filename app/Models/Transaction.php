<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['invoice_id', 'fund_request_id', 'payment_method', 'amount', 'payment_date', 'proof_of_payment'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }
}
