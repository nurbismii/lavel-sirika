<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    public const RESULT_VALID = 'valid';
    public const RESULT_EXPIRED = 'expired';
    public const RESULT_REVOKED = 'revoked';
    public const RESULT_INACTIVE = 'inactive';
    public const RESULT_INVALID = 'invalid';

    protected $fillable = [
        'permit_id',
        'scanned_by',
        'scanned_at',
        'result',
        'device_info',
        'ip_address',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function permit()
    {
        return $this->belongsTo(VehiclePermit::class, 'permit_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
