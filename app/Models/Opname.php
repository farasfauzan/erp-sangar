<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opname extends Model
{
    use HasFactory;
    protected $fillable = [
        'spk_id', 'opname_number', 'date', 
        'progress_percentage', 'progress_pct', 'progress_items',
        'amount', 'status', 'approved_by'
    ];

    protected $casts = [
        'progress_items' => 'array',
    ];

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }

    public function basts()
    {
        return $this->hasMany(\App\Models\Bast::class);
    }
}
