<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'uploaded_by',
        'total_rows',
        'success_rows',
        'failed_rows',
        'review_rows',
        'status',
        'error_summary',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function permits()
    {
        return $this->hasMany(VehiclePermit::class, 'source_import_id');
    }
}
