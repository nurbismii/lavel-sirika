<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function vehiclePermits(): BelongsToMany
    {
        return $this->belongsToMany(
            VehiclePermit::class,
            'vehicle_permit_parking_locations',
            'parking_location_id',
            'vehicle_permit_id'
        )->withTimestamps();
    }
}
