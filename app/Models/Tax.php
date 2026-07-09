<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = ['invoice_id', 'tax_invoice_number', 'tax_type', 'amount', 'is_credited', 'csv_exported_at'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
