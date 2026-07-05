<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'employee_id',
        'plate_number',
        'vehicle_type',
        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function permits()
    {
        return $this->hasMany(VehiclePermit::class);
    }
}
