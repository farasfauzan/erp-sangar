<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkProgress extends Model
{
    protected $fillable = ['spk_id', 'rab_budget_id', 'work_description', 'progress_percentage', 'amount'];

    public function spk()
    {
        return $this->belongsTo(Spk::class);
    }
}
