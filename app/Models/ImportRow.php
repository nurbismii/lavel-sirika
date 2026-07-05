<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    public const STATUS_VALID = 'valid';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_COMMITTED = 'committed';

    protected $fillable = [
        'import_batch_id',
        'row_number',
        'status',
        'raw_data',
        'normalized_data',
        'errors',
        'warnings',
        'created_employee_id',
        'created_vehicle_id',
        'created_permit_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'normalized_data' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function createdEmployee()
    {
        return $this->belongsTo(Employee::class, 'created_employee_id');
    }

    public function createdVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'created_vehicle_id');
    }

    public function createdPermit()
    {
        return $this->belongsTo(VehiclePermit::class, 'created_permit_id');
    }
}
