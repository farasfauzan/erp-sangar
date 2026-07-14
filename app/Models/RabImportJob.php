<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RabImportJob extends Model
{
    protected $fillable = [
        'project_id',
        'file_path',
        'file_name',
        'file_type',
        'sheet_name',
        'status',
        'total_rows',
        'processed_rows',
        'errors',
        'diff',
    ];

    protected $casts = [
        'errors' => 'array',
        'diff' => 'array',
    ];

    // Statuses
    const STATUS_PENDING    = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_VALIDATED  = 'VALIDATED';
    const STATUS_IMPORTING  = 'IMPORTING';
    const STATUS_COMPLETED  = 'COMPLETED';
    const STATUS_FAILED     = 'FAILED';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function rabBudgets()
    {
        return $this->hasMany(RabBudget::class);
    }
}
