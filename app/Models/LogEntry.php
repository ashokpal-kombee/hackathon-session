<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogEntry extends Model
{
    protected $fillable = [
        'analysis_id',
        'log_timestamp',
        'severity',
        'message',
        'raw_log',
        'is_duplicate'
    ];

    protected $casts = [
        'log_timestamp' => 'datetime',
        'is_duplicate' => 'boolean'
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
