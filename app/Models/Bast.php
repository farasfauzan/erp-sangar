<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bast extends Model
{
    use HasFactory;
    protected $fillable = [
        'opname_id', 'bast_number', 'bast_date', 'notes',
    ];

    public function opname()
    {
        return $this->belongsTo(Opname::class);
    }
}
