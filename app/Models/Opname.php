<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opname extends Model
{
    protected $fillable = [
        'spk_id', 'opname_number', 'date', 
        'progress_percentage', 'amount', 'status', 'approved_by'
    ];

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }
}
