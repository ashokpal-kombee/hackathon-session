<?php

namespace App\Services;

use Carbon\Carbon;

class LogPreprocessor
{
    /**
     * Preprocess logs: remove duplicates, group by time, mark severity
     */
    public function process(array $logs): array
    {
        $processed = [];
        $seen = [];
        
        foreach ($logs as $log) {
            $message = $log['message'] ?? '';
            $timestamp = $log['timestamp'] ?? now();
            
            // Parse timestamp
            $logTime = $this->parseTimestamp($timestamp);
            
            // Detect severity
            $severity = $this->detectSeverity($message);
            
            // Check for duplicates
            $hash = md5($message);
            $isDuplicate = isset($seen[$hash]);
            
            if (!$isDuplicate) {
                $seen[$hash] = true;
            }
            
            $processed[] = [
                'message' => $message,
                'log_timestamp' => $logTime,
                'severity' => $severity,
                'raw_log' => $log['raw'] ?? $message,
                'is_duplicate' => $isDuplicate
            ];
        }
        
        // Group by time windows (5-minute windows)
        return $this->groupByTimeWindow($processed);
    }
    
    private function parseTimestamp($timestamp): Carbon
    {
        if ($timestamp instanceof Carbon) {
            return $timestamp;
        }
        
        try {
            return Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return now();
        }
    }
    
    private function detectSeverity(string $message): string
    {
        $message = strtolower($message);
        
        if (str_contains($message, 'error') || str_contains($message, 'failed') || str_contains($message, 'exception')) {
            return 'error';
        }
        
        if (str_contains($message, 'warning') || str_contains($message, 'warn')) {
            return 'warning';
        }
        
        if (str_contains($message, 'critical') || str_contains($message, 'fatal')) {
            return 'critical';
        }
        
        return 'info';
    }
    
    private function groupByTimeWindow(array $logs): array
    {
        $grouped = [];
        
        foreach ($logs as $log) {
            $window = $log['log_timestamp']->format('Y-m-d H:i');
            $windowKey = substr($window, 0, -1) . '0'; // Round to 10-minute window
            
            if (!isset($grouped[$windowKey])) {
                $grouped[$windowKey] = [];
            }
            
            $grouped[$windowKey][] = $log;
        }
        
        return [
            'logs' => array_merge(...array_values($grouped)),
            'time_windows' => array_keys($grouped),
            'window_count' => count($grouped)
        ];
    }
    
    /**
     * Get summary for AI context
     */
    public function getSummary(array $processedData): array
    {
        $logs = $processedData['logs'];
        
        $severityCounts = [
            'critical' => 0,
            'error' => 0,
            'warning' => 0,
            'info' => 0
        ];
        
        $uniqueMessages = [];
        
        foreach ($logs as $log) {
            $severityCounts[$log['severity']]++;
            
            if (!$log['is_duplicate']) {
                $uniqueMessages[] = $log['message'];
            }
        }
        
        return [
            'total_logs' => count($logs),
            'severity_breakdown' => $severityCounts,
            'unique_messages' => $uniqueMessages,
            'time_windows' => $processedData['time_windows'],
            'window_count' => $processedData['window_count']
        ];
    }
}
