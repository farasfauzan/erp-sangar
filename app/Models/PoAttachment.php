<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoAttachment extends Model
{
    use HasFactory;

    protected $table = 'po_attachments';

    protected $fillable = [
        'purchase_order_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'notes',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute()
    {
        return '/storage/' . $this->file_path;
    }
}
