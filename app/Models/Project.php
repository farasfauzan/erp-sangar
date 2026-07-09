<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['project_name', 'location', 'start_date', 'end_date', 'status'];

    public function rabBudgets()
    {
        return $this->hasMany(RabBudget::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function spks()
    {
        return $this->hasMany(Spk::class);
    }
}
