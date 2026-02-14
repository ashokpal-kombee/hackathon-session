<?php

namespace Database\Seeders;

use App\Models\Analysis;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ErrorLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create analysis with realistic production errors
        $analysis = Analysis::create([
            'likely_cause' => 'Multiple system failures detected',
            'confidence' => 0.85,
            'reasoning' => 'Database connection issues, high CPU load, and memory exhaustion',
            'next_steps' => [
                'Check database connection pool',
                'Review slow queries',
                'Monitor memory usage',
                'Scale application servers'
            ],
            'status' => 'completed'
        ]);

        // Add system metrics
        $analysis->systemMetrics()->create([
            'cpu_usage' => 92.5,
            'memory_usage' => 87.3,
            'db_latency' => 450,
            'requests_per_sec' => 1500
        ]);

        // Generate realistic error logs
        $errorLogs = $this->generateErrorLogs();
        
        foreach ($errorLogs as $log) {
            $analysis->logEntries()->create($log);
        }

        $this->command->info('✅ Generated ' . count($errorLogs) . ' realistic error logs');
    }

    private function generateErrorLogs(): array
    {
        $baseTime = Carbon::now()->subHours(2);
        $logs = [];

        // 1. Database Connection Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(1),
            'severity' => 'error',
            'message' => 'SQLSTATE[HY000] [2002] Connection refused',
            'raw_log' => '[2024-02-14 10:01:23] production.ERROR: SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, SQL: select * from users)',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(2),
            'severity' => 'error',
            'message' => 'SQLSTATE[HY000] [2002] Connection refused',
            'raw_log' => '[2024-02-14 10:02:15] production.ERROR: SQLSTATE[HY000] [2002] Connection refused',
            'is_duplicate' => true
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(3),
            'severity' => 'critical',
            'message' => 'SQLSTATE[08S01]: Communication link failure',
            'raw_log' => '[2024-02-14 10:03:45] production.CRITICAL: SQLSTATE[08S01]: Communication link failure: 1153 Got a packet bigger than max_allowed_packet',
            'is_duplicate' => false
        ];

        // 2. SQL Server Specific Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(5),
            'severity' => 'error',
            'message' => 'SQLSTATE[42S02]: Invalid object name non_existent_table',
            'raw_log' => '[2024-02-14 10:05:12] production.ERROR: SQLSTATE[42S02]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Invalid object name \'non_existent_table\'. (Connection: sqlsrv, SQL: select * from [non_existent_table])',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(6),
            'severity' => 'error',
            'message' => 'SQLSTATE[23000]: Integrity constraint violation',
            'raw_log' => '[2024-02-14 10:06:33] production.ERROR: SQLSTATE[23000]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Violation of PRIMARY KEY constraint. Cannot insert duplicate key.',
            'is_duplicate' => false
        ];

        // 3. Timeout Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(8),
            'severity' => 'error',
            'message' => 'Database query timeout after 30 seconds',
            'raw_log' => '[2024-02-14 10:08:22] production.ERROR: Illuminate\\Database\\QueryException: SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(9),
            'severity' => 'error',
            'message' => 'cURL error 28: Operation timed out',
            'raw_log' => '[2024-02-14 10:09:45] production.ERROR: GuzzleHttp\\Exception\\ConnectException: cURL error 28: Operation timed out after 30000 milliseconds',
            'is_duplicate' => false
        ];

        // 4. Memory Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(12),
            'severity' => 'critical',
            'message' => 'Allowed memory size exhausted',
            'raw_log' => '[2024-02-14 10:12:11] production.CRITICAL: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(13),
            'severity' => 'error',
            'message' => 'Maximum execution time exceeded',
            'raw_log' => '[2024-02-14 10:13:55] production.ERROR: PHP Fatal error: Maximum execution time of 30 seconds exceeded',
            'is_duplicate' => false
        ];

        // 5. Application Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(15),
            'severity' => 'error',
            'message' => 'Call to undefined method',
            'raw_log' => '[2024-02-14 10:15:33] production.ERROR: Error: Call to undefined method App\\Models\\User::nonExistentMethod()',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(16),
            'severity' => 'error',
            'message' => 'Class not found',
            'raw_log' => '[2024-02-14 10:16:22] production.ERROR: Error: Class \'App\\Services\\NonExistentService\' not found',
            'is_duplicate' => false
        ];

        // 6. HTTP Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(18),
            'severity' => 'warning',
            'message' => 'HTTP 500 Internal Server Error',
            'raw_log' => '[2024-02-14 10:18:44] production.WARNING: Symfony\\Component\\HttpKernel\\Exception\\HttpException: HTTP 500 Internal Server Error',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(19),
            'severity' => 'error',
            'message' => 'Too Many Requests (429)',
            'raw_log' => '[2024-02-14 10:19:12] production.ERROR: Symfony\\Component\\HttpKernel\\Exception\\TooManyRequestsHttpException: Too Many Requests',
            'is_duplicate' => false
        ];

        // 7. Queue/Job Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(22),
            'severity' => 'error',
            'message' => 'Queue job failed after 3 attempts',
            'raw_log' => '[2024-02-14 10:22:33] production.ERROR: Illuminate\\Queue\\MaxAttemptsExceededException: App\\Jobs\\ProcessOrder has been attempted too many times',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(23),
            'severity' => 'error',
            'message' => 'Redis connection refused',
            'raw_log' => '[2024-02-14 10:23:55] production.ERROR: Predis\\Connection\\ConnectionException: Connection refused [tcp://127.0.0.1:6379]',
            'is_duplicate' => false
        ];

        // 8. File System Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(25),
            'severity' => 'error',
            'message' => 'Permission denied',
            'raw_log' => '[2024-02-14 10:25:11] production.ERROR: ErrorException: file_put_contents(/var/www/storage/logs/laravel.log): failed to open stream: Permission denied',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(26),
            'severity' => 'warning',
            'message' => 'Disk space low',
            'raw_log' => '[2024-02-14 10:26:44] production.WARNING: Disk space is running low: 95% used on /dev/sda1',
            'is_duplicate' => false
        ];

        // 9. Authentication Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(28),
            'severity' => 'warning',
            'message' => 'Failed login attempt',
            'raw_log' => '[2024-02-14 10:28:22] production.WARNING: Illuminate\\Auth\\AuthenticationException: Unauthenticated',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(29),
            'severity' => 'error',
            'message' => 'Token expired',
            'raw_log' => '[2024-02-14 10:29:55] production.ERROR: Tymon\\JWTAuth\\Exceptions\\TokenExpiredException: Token has expired',
            'is_duplicate' => false
        ];

        // 10. External API Errors
        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(32),
            'severity' => 'error',
            'message' => 'Payment gateway timeout',
            'raw_log' => '[2024-02-14 10:32:11] production.ERROR: GuzzleHttp\\Exception\\RequestException: cURL error 28: Timeout was reached connecting to payment API',
            'is_duplicate' => false
        ];

        $logs[] = [
            'log_timestamp' => $baseTime->copy()->addMinutes(33),
            'severity' => 'error',
            'message' => 'Third-party API rate limit exceeded',
            'raw_log' => '[2024-02-14 10:33:44] production.ERROR: API rate limit exceeded: 429 Too Many Requests from external service',
            'is_duplicate' => false
        ];

        return $logs;
    }
}
