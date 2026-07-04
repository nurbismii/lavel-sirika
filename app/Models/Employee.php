<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'nik',
        'name',
        'department',
        'section',
        'position',
        'division',
        'contact_number',
        'status',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function permits()
    {
        return $this->hasMany(VehiclePermit::class);
    }
}
