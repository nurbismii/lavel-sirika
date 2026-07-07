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
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'vehicle_id' => 'integer',
        'parking_location_id' => 'integer',
        'source_import_id' => 'integer',
        'reviewed_by' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'reviewed_at' => 'datetime',
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

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
        return $this->belongsToMany(
            RoadSegment::class,
            'permit_route_segments',
            'vehicle_permit_id',
            'road_segment_id'
        )
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderBy('permit_route_segments.sequence');
    }

    public function tokens()
    {
        return $this->hasMany(PermitToken::class);
    }

    public function activeToken()
    {
        return $this->hasOne(PermitToken::class)
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->latestOfMany();
    }

    public function latestToken()
    {
        return $this->hasOne(PermitToken::class)->latestOfMany();
    }
}
