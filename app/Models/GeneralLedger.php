<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    protected $fillable = ['transaction_date', 'account_code', 'description', 'debit', 'credit', 'project_id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
