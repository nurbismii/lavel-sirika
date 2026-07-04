<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePermit extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'employee_id',
        'vehicle_id',
        'parking_location_id',
        'permit_color',
        'reason',
        'approval_status',
        'valid_from',
        'valid_until',
        'status',
        'source',
        'source_import_id',
        'route_raw',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parkingLocation()
    {
        return $this->belongsTo(ParkingLocation::class);
    }

    public function sourceImport()
    {
        return $this->belongsTo(ImportBatch::class, 'source_import_id');
    }

    public function permitRouteSegments()
    {
        return $this->hasMany(PermitRouteSegment::class);
    }

    public function routeSegments()
    {
        return $this->belongsToMany(RoadSegment::class, 'permit_route_segments')
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderBy('permit_route_segments.sequence');
    }

    public function tokens()
    {
        return $this->hasMany(PermitToken::class);
    }
}
