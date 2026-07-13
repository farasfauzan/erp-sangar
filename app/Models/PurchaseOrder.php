<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'po_number',
        'date',
        'supplier_name',
        'po_type',
        'addendum_number',
        'supplier_address',
        'supplier_phone',
        'supplier_contact_person',
        'project_location',
        'discount',
        'include_ppn',
        'catatan',
        'faktur_pajak_nama',
        'faktur_pajak_npwp',
        'faktur_pajak_alamat',
        'subtotal',
        'tax_amount',
        'total_amount',
        'payment_terms',
        'status',
        'po_level',
        'routed_to',
        'routed_by',
        'routed_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'include_ppn' => 'boolean',
        'discount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(PoItem::class);
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function attachments()
    {
        return $this->hasMany(PoAttachment::class);
    }
}
