<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitToken extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'vehicle_permit_id',
        'token_hash',
        'token_encrypted',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function permit()
    {
        return $this->belongsTo(VehiclePermit::class, 'vehicle_permit_id');
    }
}
