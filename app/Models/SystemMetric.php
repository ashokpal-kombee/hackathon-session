<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMetric extends Model
{
    protected $fillable = [
        'analysis_id',
        'cpu_usage',
        'memory_usage',
        'db_latency',
        'requests_per_sec',
        'additional_metrics'
    ];

    protected $casts = [
        'additional_metrics' => 'array',
        'cpu_usage' => 'float',
        'memory_usage' => 'float',
        'db_latency' => 'float'
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
