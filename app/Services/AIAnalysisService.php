<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    private ?string $apiKey;
    private string $model;
    
    public function __construct()
    {
        // Support both OpenAI and Anthropic
        $this->apiKey = config('services.openai.api_key') ?? config('services.anthropic.api_key') ?? null;
        $this->model = config('services.ai.model', 'gpt-4');
    }
    
    /**
     * Analyze logs and metrics using AI
     * This is where MCP integration happens
     */
    public function analyze(array $logSummary, array $metrics): array
    {
        // If no API key, use fallback immediately
        if (!$this->apiKey) {
            Log::info('No AI API key configured, using fallback analysis');
            return $this->fallbackAnalysis($logSummary, $metrics);
        }
        
        $prompt = $this->buildPrompt($logSummary, $metrics);
        
        try {
            // Call AI API (OpenAI/Anthropic)
            $response = $this->callAI($prompt);
            
            return $this->parseAIResponse($response);
            
        } catch (\Exception $e) {
            Log::error('AI Analysis failed: ' . $e->getMessage());
            
            // Fallback to rule-based analysis
            return $this->fallbackAnalysis($logSummary, $metrics);
        }
    }
    
    private function buildPrompt(array $logSummary, array $metrics): string
    {
        return <<<PROMPT
You are a production incident analyzer. Analyze the following system data and suggest probable root causes.

**Log Summary:**
- Total logs: {$logSummary['total_logs']}
- Critical: {$logSummary['severity_breakdown']['critical']}
- Errors: {$logSummary['severity_breakdown']['error']}
- Warnings: {$logSummary['severity_breakdown']['warning']}
- Time windows affected: {$logSummary['window_count']}

**Unique Error Messages:**
{$this->formatMessages($logSummary['unique_messages'])}

**System Metrics:**
- CPU Usage: {$metrics['cpu_usage']}%
- Memory Usage: {$metrics['memory_usage']}%
- DB Latency: {$metrics['db_latency']}ms
- Requests/sec: {$metrics['requests_per_sec']}

**Task:**
1. Identify the most probable root cause
2. Provide confidence score (0-1)
3. Explain your reasoning
4. Suggest next steps for investigation

**Response Format (JSON):**
{
    "probable_causes": [
        {
            "cause": "string",
            "confidence": 0.0-1.0,
            "reasoning": "string",
            "evidence": ["string"]
        }
    ],
    "next_steps": ["string"],
    "correlations": ["string"]
}
PROMPT;
    }
    
    private function formatMessages(array $messages): string
    {
        return implode("\n", array_map(fn($msg, $i) => ($i + 1) . ". " . $msg, $messages, array_keys($messages)));
    }
    
    private function callAI(string $prompt): string
    {
        // OpenAI API call
        if (config('services.ai.provider') === 'openai') {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a production incident analyzer. Always respond in valid JSON format.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]);
            
            return $response->json()['choices'][0]['message']['content'];
        }
        
        // Anthropic API call
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json'
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 1000,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);
        
        return $response->json()['content'][0]['text'];
    }
    
    private function parseAIResponse(string $response): array
    {
        // Extract JSON from response
        $json = $this->extractJSON($response);
        
        if (!$json) {
            throw new \Exception('Failed to parse AI response');
        }
        
        return $json;
    }
    
    private function extractJSON(string $text): ?array
    {
        // Try to find JSON in the response
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return null;
    }
    
    /**
     * Fallback rule-based analysis when AI fails
     */
    private function fallbackAnalysis(array $logSummary, array $metrics): array
    {
        $causes = [];
        
        // Rule 1: High DB latency + DB errors
        if ($metrics['db_latency'] > 300 && $logSummary['severity_breakdown']['error'] > 0) {
            $causes[] = [
                'cause' => 'Database performance degradation',
                'confidence' => 0.75,
                'reasoning' => 'High DB latency combined with database errors',
                'evidence' => ['DB latency: ' . $metrics['db_latency'] . 'ms', 'Error count: ' . $logSummary['severity_breakdown']['error']]
            ];
        }
        
        // Rule 2: High CPU
        if ($metrics['cpu_usage'] > 80) {
            $causes[] = [
                'cause' => 'CPU resource exhaustion',
                'confidence' => 0.65,
                'reasoning' => 'CPU usage exceeds 80%',
                'evidence' => ['CPU: ' . $metrics['cpu_usage'] . '%']
            ];
        }
        
        return [
            'probable_causes' => $causes,
            'next_steps' => ['Check database connection pool', 'Review slow queries', 'Monitor resource usage'],
            'correlations' => ['High latency correlates with error spike']
        ];
    }
}
