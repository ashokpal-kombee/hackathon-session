<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Services\AIAnalysisService;
use App\Services\DecisionEngine;
use App\Services\LogFileReader;
use App\Services\LogPreprocessor;
use App\Services\LogFileImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LogAnalysisController extends Controller
{
    public function __construct(
        private LogPreprocessor $preprocessor,
        private AIAnalysisService $aiService,
        private DecisionEngine $decisionEngine,
        private LogFileReader $fileReader,
        private LogFileImporter $importer
    ) {}
    
    /**
     * POST /api/analyze
     * Main endpoint for log analysis
     */
    public function analyze(Request $request): JsonResponse
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'logs' => 'required|array|min:1',
            'logs.*.message' => 'required|string',
            'logs.*.timestamp' => 'nullable|string',
            'logs.*.raw' => 'nullable|string',
            'metrics' => 'required|array',
            'metrics.cpu_usage' => 'nullable|numeric|min:0|max:100',
            'metrics.memory_usage' => 'nullable|numeric|min:0|max:100',
            'metrics.db_latency' => 'nullable|numeric|min:0',
            'metrics.requests_per_sec' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Step 1: Create analysis record
            $analysis = Analysis::create([
                'status' => 'processing',
                'likely_cause' => 'Processing...',
                'confidence' => 0.0
            ]);
            
            // Step 2: Preprocessing
            $processedLogs = $this->preprocessor->process($request->input('logs'));
            $logSummary = $this->preprocessor->getSummary($processedLogs);
            
            // Step 3: Store logs
            foreach ($processedLogs['logs'] as $log) {
                $analysis->logEntries()->create($log);
            }
            
            // Step 4: Store metrics
            $metrics = $request->input('metrics');
            $analysis->systemMetrics()->create([
                'cpu_usage' => $metrics['cpu_usage'] ?? null,
                'memory_usage' => $metrics['memory_usage'] ?? null,
                'db_latency' => $metrics['db_latency'] ?? null,
                'requests_per_sec' => $metrics['requests_per_sec'] ?? null,
                'additional_metrics' => $metrics['additional'] ?? null
            ]);
            
            // Step 5: AI Analysis (MCP Integration Point)
            $aiSuggestions = $this->aiService->analyze($logSummary, $metrics);
            
            // Step 6: Decision Layer
            $decision = $this->decisionEngine->decide($aiSuggestions, $metrics, $logSummary);
            
            // Step 7: Update analysis with results
            $analysis->update([
                'likely_cause' => $decision['likely_cause'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'next_steps' => $decision['next_steps'],
                'ai_suggestions' => array_merge($decision['ai_suggestions'], ['rca' => $decision['rca']]),
                'correlated_signals' => $decision['correlated_signals'],
                'status' => 'completed'
            ]);
            
            DB::commit();
            
            // Step 8: Return response
            return response()->json([
                'analysis_id' => $analysis->id,
                'likely_cause' => $decision['likely_cause'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'next_steps' => $decision['next_steps'],
                'correlated_signals' => $decision['correlated_signals'],
                'metadata' => [
                    'logs_processed' => $logSummary['total_logs'],
                    'unique_errors' => count($logSummary['unique_messages']),
                    'time_windows' => $logSummary['window_count']
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Analysis failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/analysis/{id}
     * Retrieve analysis by ID
     */
    public function show(int $id): JsonResponse
    {
        $analysis = Analysis::with(['logEntries', 'systemMetrics'])->find($id);
        
        if (!$analysis) {
            return response()->json(['error' => 'Analysis not found'], 404);
        }
        
        return response()->json($analysis);
    }
    
    /**
     * GET /api/analyses
     * List all analyses with enhanced readable format
     */
    public function index(Request $request)
    {
        $analyses = Analysis::with(['logEntries', 'systemMetrics'])
            ->when($request->input('status'), fn($q, $status) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Transform data for better readability
        $transformed = $analyses->getCollection()->map(function ($analysis) {
            $errorLogs = $analysis->logEntries->where('severity', 'error');
            $criticalLogs = $analysis->logEntries->where('severity', 'critical');
            $warningLogs = $analysis->logEntries->where('severity', 'warning');
            $latestMetric = $analysis->systemMetrics->first();
            
            // Get unique error messages with counts
            $errorMessages = $analysis->logEntries
                ->whereIn('severity', ['error', 'critical'])
                ->groupBy('message')
                ->map(function ($group) {
                    return [
                        'message' => $group->first()->message,
                        'severity' => $group->first()->severity,
                        'count' => $group->count(),
                        'first_seen' => $group->first()->log_timestamp,
                        'last_seen' => $group->last()->log_timestamp,
                    ];
                })
                ->values()
                ->take(10); // Top 10 errors
            
            return [
                'id' => $analysis->id,
                'timestamp' => $analysis->created_at->format('Y-m-d H:i:s'),
                'status' => $analysis->status,
                
                // 🔴 PROBLEM SUMMARY (Easy to understand)
                'problem' => [
                    'title' => $analysis->likely_cause,
                    'severity' => $this->getSeverityLevel($criticalLogs->count(), $errorLogs->count()),
                    'confidence' => round($analysis->confidence * 100) . '%',
                    'total_errors' => $errorLogs->count(),
                    'critical_errors' => $criticalLogs->count(),
                    'warnings' => $warningLogs->count(),
                ],
                
                // ⚠️ ACTUAL ERRORS (What went wrong)
                'errors' => [
                    'summary' => "Found " . $errorMessages->count() . " unique error types",
                    'messages' => $errorMessages,
                    'sample_logs' => $analysis->logEntries
                        ->whereIn('severity', ['error', 'critical'])
                        ->take(5)
                        ->map(fn($log) => [
                            'severity' => $log->severity,
                            'message' => $log->message,
                            'timestamp' => $log->log_timestamp,
                            'raw' => $log->raw_log
                        ])
                ],
                
                // 💡 SOLUTION (What to do)
                'solution' => [
                    'next_steps' => $analysis->next_steps ?? [],
                    'reasoning' => $analysis->reasoning,
                    'ai_suggestions' => $analysis->ai_suggestions ?? [],
                ],
                
                // 📋 ROOT CAUSE ANALYSIS (RCA)
                'rca' => $this->extractRCA($analysis),
                
                // 📊 SYSTEM HEALTH
                'system_health' => $latestMetric ? [
                    'cpu' => $latestMetric->cpu_usage . '%',
                    'memory' => $latestMetric->memory_usage . '%',
                    'db_latency' => $latestMetric->db_latency . 'ms',
                    'requests_per_sec' => $latestMetric->requests_per_sec,
                ] : null,
                
                // 🔗 CORRELATIONS
                'correlations' => $analysis->correlated_signals ?? [],
                
                // 📝 QUICK SUMMARY (One-liner)
                'summary' => $this->generateQuickSummary($analysis, $errorLogs->count(), $criticalLogs->count()),
            ];
        });
        
        // Check if HTML view is requested
        if ($request->input('view_type') === 'html') {
            return view('analysis-report', [
                'data' => $transformed,
                'pagination' => [
                    'current_page' => $analyses->currentPage(),
                    'total' => $analyses->total(),
                    'per_page' => $analyses->perPage(),
                    'last_page' => $analyses->lastPage(),
                ]
            ]);
        }
        
        // Default JSON response
        return response()->json([
            'success' => true,
            'data' => $transformed,
            'pagination' => [
                'current_page' => $analyses->currentPage(),
                'total' => $analyses->total(),
                'per_page' => $analyses->perPage(),
                'last_page' => $analyses->lastPage(),
            ]
        ]);
    }
    
    /**
     * Determine severity level based on error counts
     */
    private function getSeverityLevel(int $critical, int $errors): string
    {
        if ($critical > 0) return '🔴 CRITICAL';
        if ($errors > 10) return '🟠 HIGH';
        if ($errors > 0) return '🟡 MEDIUM';
        return '🟢 LOW';
    }
    
    /**
     * Generate a quick one-line summary
     */
    private function generateQuickSummary(Analysis $analysis, int $errors, int $critical): string
    {
        $severity = $critical > 0 ? 'Critical' : ($errors > 10 ? 'High' : 'Medium');
        return "{$severity} issue detected: {$analysis->likely_cause} (Confidence: " . round($analysis->confidence * 100) . "%)";
    }
    
    /**
     * Extract RCA from analysis data
     */
    private function extractRCA(Analysis $analysis): ?array
    {
        // Check if RCA data exists in ai_suggestions
        $aiSuggestions = $analysis->ai_suggestions;
        
        if (is_array($aiSuggestions) && isset($aiSuggestions['rca'])) {
            return $aiSuggestions['rca'];
        }
        
        // Generate RCA on-the-fly for old analyses
        return $this->generateRCAOnTheFly($analysis);
    }
    
    /**
     * Generate RCA on-the-fly for old analyses that don't have it
     */
    private function generateRCAOnTheFly(Analysis $analysis): array
    {
        $logSummary = [
            'total_logs' => $analysis->logEntries->count(),
            'severity_breakdown' => [
                'critical' => $analysis->logEntries->where('severity', 'critical')->count(),
                'error' => $analysis->logEntries->where('severity', 'error')->count(),
                'warning' => $analysis->logEntries->where('severity', 'warning')->count(),
            ],
            'window_count' => 1,
            'unique_messages' => $analysis->logEntries->pluck('message')->unique()->toArray()
        ];
        
        $metrics = $analysis->systemMetrics->first();
        $metricsArray = $metrics ? [
            'cpu_usage' => $metrics->cpu_usage ?? 0,
            'memory_usage' => $metrics->memory_usage ?? 0,
            'db_latency' => $metrics->db_latency ?? 0,
            'requests_per_sec' => $metrics->requests_per_sec ?? 0,
        ] : [
            'cpu_usage' => 0,
            'memory_usage' => 0,
            'db_latency' => 0,
            'requests_per_sec' => 0,
        ];
        
        $topCause = [
            'cause' => $analysis->likely_cause,
            'confidence' => $analysis->confidence,
            'reasoning' => $analysis->reasoning ?? 'Analysis completed',
            'evidence' => []
        ];
        
        // Use DecisionEngine to build RCA
        $decisionEngine = app(\App\Services\DecisionEngine::class);
        $decision = $decisionEngine->decide(
            ['probable_causes' => [$topCause]],
            $metricsArray,
            $logSummary
        );
        
        return $decision['rca'] ?? null;
    }
    
    /**
     * POST /api/analyze-file
     * Analyze logs from file
     */
    public function analyzeFromFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source' => 'required|string|in:laravel,storage,path',
            'file_path' => 'required_if:source,path,storage|string',
            'lines' => 'nullable|integer|min:1|max:1000',
            'metrics' => 'required|array',
            'metrics.cpu_usage' => 'nullable|numeric|min:0|max:100',
            'metrics.memory_usage' => 'nullable|numeric|min:0|max:100',
            'metrics.db_latency' => 'nullable|numeric|min:0',
            'metrics.requests_per_sec' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }
        
        try {
            // Read logs from file
            $logs = match($request->input('source')) {
                'laravel' => $this->fileReader->readLaravelLogs($request->input('lines', 100)),
                'storage' => $this->fileReader->readFromStorage($request->input('file_path')),
                'path' => $this->fileReader->readFromFile($request->input('file_path')),
            };
            
            if (empty($logs)) {
                return response()->json([
                    'error' => 'No logs found in file'
                ], 404);
            }
            
            // Now process like normal analyze endpoint
            DB::beginTransaction();
            
            $analysis = Analysis::create([
                'status' => 'processing',
                'likely_cause' => 'Processing...',
                'confidence' => 0.0
            ]);
            
            // Preprocessing
            $processedLogs = $this->preprocessor->process($logs);
            $logSummary = $this->preprocessor->getSummary($processedLogs);
            
            // Store logs
            foreach ($processedLogs['logs'] as $log) {
                $analysis->logEntries()->create($log);
            }
            
            // Store metrics
            $metrics = $request->input('metrics');
            $analysis->systemMetrics()->create([
                'cpu_usage' => $metrics['cpu_usage'] ?? null,
                'memory_usage' => $metrics['memory_usage'] ?? null,
                'db_latency' => $metrics['db_latency'] ?? null,
                'requests_per_sec' => $metrics['requests_per_sec'] ?? null,
                'additional_metrics' => $metrics['additional'] ?? null
            ]);
            
            // AI Analysis
            $aiSuggestions = $this->aiService->analyze($logSummary, $metrics);
            
            // Decision Layer
            $decision = $this->decisionEngine->decide($aiSuggestions, $metrics, $logSummary);
            
            // Update analysis
            $analysis->update([
                'likely_cause' => $decision['likely_cause'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'next_steps' => $decision['next_steps'],
                'ai_suggestions' => array_merge($decision['ai_suggestions'], ['rca' => $decision['rca']]),
                'correlated_signals' => $decision['correlated_signals'],
                'status' => 'completed'
            ]);
            
            DB::commit();
            
            return response()->json([
                'analysis_id' => $analysis->id,
                'likely_cause' => $decision['likely_cause'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'next_steps' => $decision['next_steps'],
                'correlated_signals' => $decision['correlated_signals'],
                'metadata' => [
                    'logs_processed' => $logSummary['total_logs'],
                    'unique_errors' => count($logSummary['unique_messages']),
                    'time_windows' => $logSummary['window_count'],
                    'source' => $request->input('source')
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Analysis failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/logs/import
     * Import log file and store in database
     */
    public function importLogFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:log,txt|max:10240', // Max 10MB
            'analysis_id' => 'nullable|exists:analyses,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            $file = $request->file('file');
            $filePath = $file->getRealPath();
            $analysisId = $request->input('analysis_id');
            
            // If no analysis_id provided, create a new analysis
            if (!$analysisId) {
                $analysis = Analysis::create([
                    'status' => 'imported',
                    'likely_cause' => 'Log file imported: ' . $file->getClientOriginalName(),
                    'confidence' => 0.0,
                    'reasoning' => 'Logs imported from file, analysis pending',
                    'next_steps' => ['Review imported logs', 'Run analysis if needed']
                ]);
                $analysisId = $analysis->id;
            }
            
            // Import logs with analysis_id
            $result = $this->importer->import($filePath, $analysisId);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Log file imported successfully',
                'data' => [
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'total_lines' => $result['imported'] + $result['skipped'],
                    'analysis_id' => $analysisId,
                    'sample_errors' => $result['sample_errors'] ?? [],
                    'note' => $result['skipped'] > 0 ? 'Some lines were skipped. Use /api/logs/debug-import to see details.' : null
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Import failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/logs/debug-import
     * Debug log file parsing without importing
     */
    public function debugImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:log,txt|max:10240',
            'lines' => 'nullable|integer|min:1|max:50'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }
        
        try {
            $file = $request->file('file');
            $filePath = $file->getRealPath();
            $maxLines = $request->input('lines', 10);
            
            $debugInfo = [];
            $handle = fopen($filePath, 'r');
            $lineNumber = 0;
            
            while (($line = fgets($handle)) !== false && $lineNumber < $maxLines) {
                $lineNumber++;
                $line = trim($line);
                
                if (empty($line)) {
                    continue;
                }
                
                try {
                    // Try to parse using LogFileImporter logic
                    $parsed = $this->debugParseLine($line);
                    
                    $debugInfo[] = [
                        'line_number' => $lineNumber,
                        'original' => substr($line, 0, 150),
                        'parsed' => $parsed,
                        'status' => 'success'
                    ];
                } catch (\Exception $e) {
                    $debugInfo[] = [
                        'line_number' => $lineNumber,
                        'original' => substr($line, 0, 150),
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ];
                }
            }
            
            fclose($handle);
            
            $successCount = count(array_filter($debugInfo, fn($item) => $item['status'] === 'success'));
            $failedCount = count(array_filter($debugInfo, fn($item) => $item['status'] === 'failed'));
            
            return response()->json([
                'success' => true,
                'summary' => [
                    'total_lines_checked' => count($debugInfo),
                    'successfully_parsed' => $successCount,
                    'failed_to_parse' => $failedCount,
                    'success_rate' => count($debugInfo) > 0 ? round(($successCount / count($debugInfo)) * 100, 2) . '%' : '0%'
                ],
                'sample_lines' => $debugInfo,
                'recommendation' => $failedCount > 0 ? 'Check the failed lines format. You may need to adjust your log format.' : 'All sample lines parsed successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Debug parse a single line (same logic as LogFileImporter)
     */
    private function debugParseLine(string $line): array
    {
        // Format 1: Laravel log format [2024-02-14 10:30:45] environment.LEVEL: message
        if (preg_match('/\[([\d\-\s:]+)\]\s+\w+\.(\w+):\s+(.+)/s', $line, $matches)) {
            return [
                'format' => 'Laravel',
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => substr(trim($matches[3]), 0, 100)
            ];
        }
        
        // Format 2: [2024-02-14 10:30:45] message (without environment)
        if (preg_match('/\[([\d\-\s:]+)\]\s+(.+)/s', $line, $matches)) {
            return [
                'format' => 'Bracketed timestamp',
                'timestamp' => $matches[1],
                'severity' => $this->detectSeverity($matches[2]),
                'message' => substr(trim($matches[2]), 0, 100)
            ];
        }
        
        // Format 3: 2024-02-14 10:30:45 LEVEL: message (without brackets)
        if (preg_match('/([\d\-\s:]+)\s+(ERROR|WARNING|INFO|CRITICAL|DEBUG|NOTICE):\s+(.+)/i', $line, $matches)) {
            return [
                'format' => 'Plain timestamp with level',
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => substr(trim($matches[3]), 0, 100)
            ];
        }
        
        // Format 4: Timestamp at start (YYYY-MM-DD HH:MM:SS or similar)
        if (preg_match('/^([\d\-\/\s:]+)\s+(.+)/s', $line, $matches)) {
            $timestamp = $matches[1];
            $message = $matches[2];
            
            if (strlen($timestamp) >= 10) {
                return [
                    'format' => 'Timestamp prefix',
                    'timestamp' => $timestamp,
                    'severity' => $this->detectSeverity($message),
                    'message' => substr(trim($message), 0, 100)
                ];
            }
        }
        
        // Format 5: Generic - no timestamp found
        return [
            'format' => 'Generic (no timestamp)',
            'timestamp' => 'current time',
            'severity' => $this->detectSeverity($line),
            'message' => substr(trim($line), 0, 100)
        ];
    }
    
    private function detectSeverity(string $line): string
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
