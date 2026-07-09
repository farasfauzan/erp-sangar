<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    protected $fillable = ['record_type', 'record_id', 'user_id', 'action', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
