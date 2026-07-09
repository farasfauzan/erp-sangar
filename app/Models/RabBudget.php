<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RabBudget extends Model
{
    protected $fillable = ['project_id', 'code_item', 'description', 'unit', 'volume', 'unit_price', 'total_price', 'category'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
