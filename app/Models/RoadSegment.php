<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadSegment extends Model
{
    protected $fillable = [
        'code',
        'name',
        'start_location',
        'end_location',
        'polyline_json',
        'status',
    ];

    protected $casts = [
        'polyline_json' => 'array',
    ];

    public function permitRoutes()
    {
        return $this->hasMany(PermitRouteSegment::class);
    }
}
