<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingLocation extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public function permits()
    {
        return $this->hasMany(VehiclePermit::class);
    }
}
