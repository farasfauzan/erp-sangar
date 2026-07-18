<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RabImportDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'file_id', 'file_fingerprint', 'original_name', 'sheet',
        'row_number', 'code_item', 'description', 'unit', 'volume', 'unit_price',
        'total_price', 'category', 'item_group', 'status', 'saved_budget_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function savedBudget()
    {
        return $this->belongsTo(RabBudget::class, 'saved_budget_id')->withTrashed();
    }
}
