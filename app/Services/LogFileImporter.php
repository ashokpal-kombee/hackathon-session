<?php

namespace App\Services;

use App\Models\LogEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogFileImporter
{
    /**
     * Import log file and store entries in database
     */
    public function import(string $filePath, ?int $analysisId = null): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        try {
            DB::beginTransaction();
            
            $file = fopen($filePath, 'r');
            $lineNumber = 0;
            
            while (($line = fgets($file)) !== false) {
                $lineNumber++;
                $line = trim($line);
                
                // Skip empty lines
                if (empty($line)) {
                    continue;
                }
                
                try {
                    $parsed = $this->parseLogLine($line);
                    
                    LogEntry::create([
                        'analysis_id' => $analysisId,
                        'log_timestamp' => $parsed['timestamp'],
                        'severity' => $parsed['severity'],
                        'message' => $parsed['message'],
                        'raw_log' => $line,
                        'is_duplicate' => false
                    ]);
                    
                    $imported++;
                    
                } catch (\Exception $e) {
                    $skipped++;
                    // Store first 10 errors for debugging
                    if (count($errors) < 10) {
                        $errors[] = [
                            'line_number' => $lineNumber,
                            'content' => substr($line, 0, 100),
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
            
            fclose($file);
            
            DB::commit();
            
            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'sample_errors' => array_slice($errors, 0, 5) // First 5 errors for quick debug
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Parse a single log line
     */
    private function parseLogLine(string $line): array
    {
        // Format 1: Laravel log format [2024-02-14 10:30:45] environment.LEVEL: message
        if (preg_match('/\[([\d\-\s:]+)\]\s+\w+\.(\w+):\s+(.+)/s', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => trim($matches[3])
            ];
        }
        
        // Format 2: [2024-02-14 10:30:45] message (without environment)
        if (preg_match('/\[([\d\-\s:]+)\]\s+(.+)/s', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => $this->detectSeverityFromLine($matches[2]),
                'message' => trim($matches[2])
            ];
        }
        
        // Format 3: 2024-02-14 10:30:45 LEVEL: message (without brackets)
        if (preg_match('/([\d\-\s:]+)\s+(ERROR|WARNING|INFO|CRITICAL|DEBUG|NOTICE):\s+(.+)/i', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => trim($matches[3])
            ];
        }
        
        // Format 4: Apache/Nginx format with date
        if (preg_match('/\[([\d\/\w:+\s]+)\]/', $line, $matches)) {
            return [
                'timestamp' => now(),
                'severity' => $this->detectSeverityFromLine($line),
                'message' => trim($line)
            ];
        }
        
        // Format 5: Timestamp at start (YYYY-MM-DD HH:MM:SS or similar)
        if (preg_match('/^([\d\-\/\s:]+)\s+(.+)/s', $line, $matches)) {
            $timestamp = $matches[1];
            $message = $matches[2];
            
            // Validate timestamp format
            if (strlen($timestamp) >= 10) {
                return [
                    'timestamp' => $timestamp,
                    'severity' => $this->detectSeverityFromLine($message),
                    'message' => trim($message)
                ];
            }
        }
        
        // Format 6: Generic - no timestamp found, use current time
        return [
            'timestamp' => now(),
            'severity' => $this->detectSeverityFromLine($line),
            'message' => trim($line)
        ];
    }
    
    /**
     * Detect severity from log line content
     */
    private function detectSeverityFromLine(string $line): string
    {
        $lineLower = strtolower($line);
        
        if (str_contains($lineLower, 'critical') || str_contains($lineLower, 'fatal')) {
            return 'critical';
        }
        
        if (str_contains($lineLower, 'error') || str_contains($lineLower, 'exception')) {
            return 'error';
        }
        
        if (str_contains($lineLower, 'warning') || str_contains($lineLower, 'warn')) {
            return 'warning';
        }
        
        if (str_contains($lineLower, 'info') || str_contains($lineLower, 'notice')) {
            return 'info';
        }
        
        return 'info';
    }
}
