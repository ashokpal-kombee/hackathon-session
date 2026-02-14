<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    protected $fillable = [
        'likely_cause',
        'confidence',
        'reasoning',
        'next_steps',
        'ai_suggestions',
        'correlated_signals',
        'status'
    ];

    protected $casts = [
        'next_steps' => 'array',
        'ai_suggestions' => 'array',
        'correlated_signals' => 'array',
        'confidence' => 'float'
    ];

    public function logEntries(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    public function systemMetrics(): HasMany
    {
        return $this->hasMany(SystemMetric::class);
    }
}
