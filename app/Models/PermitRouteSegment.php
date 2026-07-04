<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitRouteSegment extends Model
{
    protected $fillable = [
        'vehicle_permit_id',
        'road_segment_id',
        'sequence',
    ];

    public function permit()
    {
        return $this->belongsTo(VehiclePermit::class, 'vehicle_permit_id');
    }

    public function roadSegment()
    {
        return $this->belongsTo(RoadSegment::class);
    }
}
