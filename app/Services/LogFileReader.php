<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class LogFileReader
{
    /**
     * Read logs from Laravel log file
     */
    public function readLaravelLogs(int $lines = 100): array
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $logs = [];
        $file = new \SplFileObject($logPath);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        // Read last N lines
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = trim($file->fgets());
            
            if (empty($line)) {
                continue;
            }
            
            $parsed = $this->parseLogLine($line);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }
        
        return $logs;
    }
    
    /**
     * Read logs from custom file
     */
    public function readFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Log file not found: {$filePath}");
        }
        
        $logs = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $parsed = $this->parseLogLine($line);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }
        
        return $logs;
    }
    
    /**
     * Parse Laravel log format
     * Example: [2024-02-14 12:00:00] local.ERROR: Database timeout
     */
    private function parseLogLine(string $line): ?array
    {
        // Laravel log format
        if (preg_match('/\[(.*?)\]\s+\w+\.(\w+):\s+(.*)/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => $matches[3],
                'raw' => $line
            ];
        }
        
        // Generic log format
        if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}).*?(\w+):\s+(.*)/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => $matches[3],
                'raw' => $line
            ];
        }
        
        // Plain text (no timestamp)
        return [
            'timestamp' => now()->toDateTimeString(),
            'severity' => 'info',
            'message' => $line,
            'raw' => $line
        ];
    }
    
    /**
     * Read logs from storage disk
     */
    public function readFromStorage(string $path): array
    {
        if (!Storage::exists($path)) {
            throw new \Exception("Log file not found in storage: {$path}");
        }
        
        $content = Storage::get($path);
        $lines = explode("\n", $content);
        
        $logs = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parsed = $this->parseLogLine($line);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }
        
        return $logs;
    }
}
