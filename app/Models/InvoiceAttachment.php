<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAttachment extends Model
{
    protected $fillable = ['invoice_id', 'doc_type', 'file_path'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
