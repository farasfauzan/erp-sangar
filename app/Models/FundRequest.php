<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRequest extends Model
{
    protected $fillable = ['project_id', 'request_number', 'amount', 'description', 'status', 'requested_by'];
}
