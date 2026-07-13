<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadSegment extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
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
