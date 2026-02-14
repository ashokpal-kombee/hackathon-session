<?php

namespace App\Services;

class DecisionEngine
{
    /**
     * Backend decision layer: rank causes, add confidence, correlate with metrics
     * Now includes full RCA (Root Cause Analysis) format
     */
    public function decide(array $aiSuggestions, array $metrics, array $logSummary): array
    {
        $causes = $aiSuggestions['probable_causes'] ?? [];
        
        // Enhance each cause with correlation analysis
        $rankedCauses = array_map(function($cause) use ($metrics, $logSummary) {
            return $this->enhanceCause($cause, $metrics, $logSummary);
        }, $causes);
        
        // Sort by confidence (descending)
        usort($rankedCauses, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        // Select the top cause
        $topCause = $rankedCauses[0] ?? $this->getDefaultCause();
        
        // Build complete RCA
        $rca = $this->buildRCA($topCause, $metrics, $logSummary, $aiSuggestions);
        
        return [
            'likely_cause' => $topCause['cause'],
            'confidence' => $topCause['confidence'],
            'reasoning' => $topCause['reasoning'],
            'next_steps' => $this->generateNextSteps($topCause, $metrics),
            'ai_suggestions' => $aiSuggestions,
            'correlated_signals' => $this->getCorrelatedSignals($metrics, $logSummary),
            'all_causes' => $rankedCauses,
            'rca' => $rca  // Full RCA format
        ];
    }
    
    /**
     * Build complete Root Cause Analysis (RCA)
     */
    private function buildRCA(array $topCause, array $metrics, array $logSummary, array $aiSuggestions): array
    {
        return [
            'root_cause' => $topCause['cause'],
            'confidence' => $topCause['confidence'],
            
            'timeline' => $this->buildTimeline($logSummary),
            
            'impact' => $this->assessImpact($logSummary, $metrics),
            
            'five_whys' => $this->generateFiveWhys($topCause, $metrics, $logSummary),
            
            'contributing_factors' => $this->identifyContributingFactors($topCause, $metrics, $logSummary),
            
            'evidence' => $topCause['evidence'] ?? [],
            
            'immediate_actions' => $this->generateImmediateActions($topCause, $metrics),
            
            'prevention_steps' => $this->generatePreventionSteps($topCause, $metrics),
            
            'lessons_learned' => $this->generateLessonsLearned($topCause, $metrics, $logSummary),
        ];
    }
    
    /**
     * Build incident timeline
     */
    private function buildTimeline(array $logSummary): array
    {
        $now = now();
        $duration = $logSummary['window_count'] * 5; // Assuming 5-minute windows
        
        return [
            'incident_detected' => $now->format('Y-m-d H:i:s'),
            'estimated_start' => $now->subMinutes($duration)->format('Y-m-d H:i:s'),
            'duration_minutes' => $duration,
            'time_windows_affected' => $logSummary['window_count'],
            'status' => 'analyzed'
        ];
    }
    
    /**
     * Assess incident impact
     */
    private function assessImpact(array $logSummary, array $metrics): array
    {
        $totalErrors = $logSummary['severity_breakdown']['error'] + $logSummary['severity_breakdown']['critical'];
        $criticalCount = $logSummary['severity_breakdown']['critical'];
        
        // Determine severity
        $severity = 'LOW';
        if ($criticalCount > 0) {
            $severity = 'CRITICAL';
        } elseif ($totalErrors > 10) {
            $severity = 'HIGH';
        } elseif ($totalErrors > 0) {
            $severity = 'MEDIUM';
        }
        
        // Estimate affected requests
        $errorRate = $totalErrors / max(1, $logSummary['total_logs']) * 100;
        $estimatedAffectedRequests = round($metrics['requests_per_sec'] * $logSummary['window_count'] * 5 * ($errorRate / 100));
        
        return [
            'severity' => $severity,
            'total_errors' => $totalErrors,
            'critical_errors' => $criticalCount,
            'error_rate_percentage' => round($errorRate, 2),
            'estimated_affected_requests' => $estimatedAffectedRequests,
            'business_impact' => $this->getBusinessImpact($severity, $totalErrors),
            'system_health' => [
                'cpu_status' => $this->getHealthStatus($metrics['cpu_usage'], 80, 90),
                'memory_status' => $this->getHealthStatus($metrics['memory_usage'], 80, 90),
                'db_status' => $this->getHealthStatus($metrics['db_latency'], 300, 500, true)
            ]
        ];
    }
    
    /**
     * Generate 5 Whys analysis
     */
    private function generateFiveWhys(array $cause, array $metrics, array $logSummary): array
    {
        $causeLower = strtolower($cause['cause']);
        $whys = [];
        
        // Database-related 5 Whys
        if (str_contains($causeLower, 'database') || str_contains($causeLower, 'connection')) {
            $whys = [
                ['why' => 'Why did the application fail?', 'answer' => 'Database connections timed out'],
                ['why' => 'Why did connections timeout?', 'answer' => 'Database was slow to respond (latency: ' . $metrics['db_latency'] . 'ms)'],
                ['why' => 'Why was database slow?', 'answer' => 'High query load or inefficient queries'],
                ['why' => 'Why was there high query load?', 'answer' => 'Traffic spike (' . $metrics['requests_per_sec'] . ' req/sec)'],
                ['why' => 'Why couldn\'t system handle the load?', 'answer' => 'Connection pool or query optimization not configured for peak load']
            ];
        }
        // CPU-related 5 Whys
        elseif (str_contains($causeLower, 'cpu') || str_contains($causeLower, 'resource')) {
            $whys = [
                ['why' => 'Why is the application slow?', 'answer' => 'CPU usage is high (' . $metrics['cpu_usage'] . '%)'],
                ['why' => 'Why is CPU usage high?', 'answer' => 'Processing too many requests or inefficient code'],
                ['why' => 'Why is code inefficient?', 'answer' => 'Possible infinite loops, heavy computations, or missing caching'],
                ['why' => 'Why wasn\'t this caught earlier?', 'answer' => 'Insufficient load testing or monitoring'],
                ['why' => 'Why no monitoring alerts?', 'answer' => 'Alert thresholds not configured or too high']
            ];
        }
        // Memory-related 5 Whys
        elseif (str_contains($causeLower, 'memory') || str_contains($causeLower, 'oom')) {
            $whys = [
                ['why' => 'Why did the application crash?', 'answer' => 'Out of memory error (usage: ' . $metrics['memory_usage'] . '%)'],
                ['why' => 'Why did memory run out?', 'answer' => 'Memory leak or large data processing'],
                ['why' => 'Why is there a memory leak?', 'answer' => 'Objects not being garbage collected or caching issues'],
                ['why' => 'Why weren\'t objects released?', 'answer' => 'Improper resource management in code'],
                ['why' => 'Why wasn\'t this detected?', 'answer' => 'No memory profiling or monitoring in place']
            ];
        }
        // Generic 5 Whys
        else {
            $whys = [
                ['why' => 'Why did the issue occur?', 'answer' => $cause['cause']],
                ['why' => 'Why did this cause problems?', 'answer' => $cause['reasoning']],
                ['why' => 'Why wasn\'t it prevented?', 'answer' => 'Insufficient monitoring or testing'],
                ['why' => 'Why no monitoring?', 'answer' => 'Alert thresholds not configured'],
                ['why' => 'Why not configured?', 'answer' => 'Needs review of monitoring strategy']
            ];
        }
        
        return $whys;
    }
    
    /**
     * Identify contributing factors
     */
    private function identifyContributingFactors(array $cause, array $metrics, array $logSummary): array
    {
        $factors = [];
        
        // High traffic
        if ($metrics['requests_per_sec'] > 500) {
            $factors[] = 'High traffic load (' . $metrics['requests_per_sec'] . ' requests/sec)';
        }
        
        // Resource constraints
        if ($metrics['cpu_usage'] > 75) {
            $factors[] = 'Elevated CPU usage (' . $metrics['cpu_usage'] . '%)';
        }
        if ($metrics['memory_usage'] > 80) {
            $factors[] = 'High memory usage (' . $metrics['memory_usage'] . '%)';
        }
        if ($metrics['db_latency'] > 300) {
            $factors[] = 'Slow database response (' . $metrics['db_latency'] . 'ms)';
        }
        
        // Error patterns
        if ($logSummary['severity_breakdown']['critical'] > 0) {
            $factors[] = 'Critical errors present (' . $logSummary['severity_breakdown']['critical'] . ' occurrences)';
        }
        
        // Multiple time windows
        if ($logSummary['window_count'] > 3) {
            $factors[] = 'Sustained issue across ' . $logSummary['window_count'] . ' time windows';
        }
        
        // Add cause-specific factors
        $causeLower = strtolower($cause['cause']);
        if (str_contains($causeLower, 'connection')) {
            $factors[] = 'Possible connection pool exhaustion';
        }
        if (str_contains($causeLower, 'timeout')) {
            $factors[] = 'Network or query timeout issues';
        }
        
        return $factors;
    }
    
    /**
     * Generate immediate action items
     */
    private function generateImmediateActions(array $cause, array $metrics): array
    {
        $actions = [];
        $causeLower = strtolower($cause['cause']);
        
        // Database actions
        if (str_contains($causeLower, 'database') || str_contains($causeLower, 'connection')) {
            $actions[] = '🔴 URGENT: Increase database connection pool size';
            $actions[] = '🔴 Check database server health and resources';
            $actions[] = '🟡 Review and kill long-running queries';
            $actions[] = '🟡 Enable query caching if not already enabled';
        }
        
        // CPU actions
        if (str_contains($causeLower, 'cpu') || $metrics['cpu_usage'] > 80) {
            $actions[] = '🔴 URGENT: Scale up application servers';
            $actions[] = '🟡 Profile application to identify CPU hotspots';
            $actions[] = '🟡 Check for infinite loops or heavy computations';
        }
        
        // Memory actions
        if (str_contains($causeLower, 'memory') || $metrics['memory_usage'] > 85) {
            $actions[] = '🔴 URGENT: Restart affected services to free memory';
            $actions[] = '🟡 Check for memory leaks';
            $actions[] = '🟡 Review caching strategy';
        }
        
        // Generic actions
        if (empty($actions)) {
            $actions[] = '🟡 Review application logs for detailed error messages';
            $actions[] = '🟡 Check system resources (CPU, Memory, Disk)';
            $actions[] = '🟡 Verify external service dependencies';
        }
        
        return $actions;
    }
    
    /**
     * Generate prevention steps
     */
    private function generatePreventionSteps(array $cause, array $metrics): array
    {
        $steps = [];
        $causeLower = strtolower($cause['cause']);
        
        // Database prevention
        if (str_contains($causeLower, 'database')) {
            $steps[] = 'Implement auto-scaling for database connection pool';
            $steps[] = 'Set up monitoring alerts for DB latency > 200ms';
            $steps[] = 'Regular database query optimization reviews';
            $steps[] = 'Implement read replicas for load distribution';
        }
        
        // Performance prevention
        if (str_contains($causeLower, 'cpu') || str_contains($causeLower, 'performance')) {
            $steps[] = 'Implement horizontal auto-scaling based on CPU metrics';
            $steps[] = 'Regular performance profiling and optimization';
            $steps[] = 'Load testing before major releases';
            $steps[] = 'Implement caching strategy (Redis/Memcached)';
        }
        
        // Memory prevention
        if (str_contains($causeLower, 'memory')) {
            $steps[] = 'Regular memory profiling to detect leaks';
            $steps[] = 'Implement memory limits and monitoring';
            $steps[] = 'Review object lifecycle and garbage collection';
        }
        
        // General prevention
        $steps[] = 'Set up comprehensive monitoring and alerting';
        $steps[] = 'Implement circuit breakers for external dependencies';
        $steps[] = 'Regular disaster recovery drills';
        $steps[] = 'Document runbooks for common incidents';
        
        return array_unique($steps);
    }
    
    /**
     * Generate lessons learned
     */
    private function generateLessonsLearned(array $cause, array $metrics, array $logSummary): array
    {
        $lessons = [];
        $causeLower = strtolower($cause['cause']);
        
        // Configuration lessons
        if (str_contains($causeLower, 'connection') || str_contains($causeLower, 'pool')) {
            $lessons[] = 'Connection pool size was not optimized for peak load';
            $lessons[] = 'Need better capacity planning for database connections';
        }
        
        // Monitoring lessons
        if ($logSummary['window_count'] > 2) {
            $lessons[] = 'Issue persisted for ' . ($logSummary['window_count'] * 5) . ' minutes before detection';
            $lessons[] = 'Need faster alerting for critical errors';
        }
        
        // Resource lessons
        if ($metrics['cpu_usage'] > 80 || $metrics['memory_usage'] > 80) {
            $lessons[] = 'Resource thresholds need to be reviewed and adjusted';
            $lessons[] = 'Auto-scaling should trigger earlier';
        }
        
        // Testing lessons
        $lessons[] = 'Load testing scenarios should include this failure mode';
        $lessons[] = 'Need better monitoring dashboards for quick diagnosis';
        
        return $lessons;
    }
    
    /**
     * Get health status label
     */
    private function getHealthStatus($value, $warningThreshold, $criticalThreshold, $inverse = false): string
    {
        if ($inverse) {
            // For metrics where higher is worse (like latency)
            if ($value >= $criticalThreshold) return '🔴 Critical';
            if ($value >= $warningThreshold) return '🟡 Warning';
            return '🟢 Healthy';
        } else {
            // For metrics where higher is worse (like CPU %)
            if ($value >= $criticalThreshold) return '🔴 Critical';
            if ($value >= $warningThreshold) return '🟡 Warning';
            return '🟢 Healthy';
        }
    }
    
    /**
     * Get business impact description
     */
    private function getBusinessImpact(string $severity, int $errorCount): string
    {
        return match($severity) {
            'CRITICAL' => 'Severe service degradation - immediate action required',
            'HIGH' => 'Significant impact on user experience',
            'MEDIUM' => 'Moderate impact - some users affected',
            'LOW' => 'Minor impact - isolated incidents',
            default => 'Impact assessment in progress'
        };
    }
    
    private function enhanceCause(array $cause, array $metrics, array $logSummary): array
    {
        $confidence = $cause['confidence'];
        
        // Boost confidence based on metric correlation
        $correlationBoost = $this->calculateCorrelationBoost($cause, $metrics, $logSummary);
        
        $cause['confidence'] = min(0.99, $confidence + $correlationBoost);
        $cause['correlation_boost'] = $correlationBoost;
        
        return $cause;
    }
    
    private function calculateCorrelationBoost(array $cause, array $metrics, array $logSummary): float
    {
        $boost = 0.0;
        $causeLower = strtolower($cause['cause']);
        
        // Database-related causes
        if (str_contains($causeLower, 'database') || str_contains($causeLower, 'db')) {
            if ($metrics['db_latency'] > 300) {
                $boost += 0.1;
            }
            if ($metrics['db_latency'] > 500) {
                $boost += 0.05;
            }
            if ($logSummary['severity_breakdown']['error'] > 5) {
                $boost += 0.05;
            }
        }
        
        // CPU-related causes
        if (str_contains($causeLower, 'cpu') || str_contains($causeLower, 'resource')) {
            if ($metrics['cpu_usage'] > 80) {
                $boost += 0.1;
            }
            if ($metrics['requests_per_sec'] > 1000) {
                $boost += 0.05;
            }
        }
        
        // Memory-related causes
        if (str_contains($causeLower, 'memory') || str_contains($causeLower, 'oom')) {
            if ($metrics['memory_usage'] > 85) {
                $boost += 0.15;
            }
        }
        
        // Network/timeout causes
        if (str_contains($causeLower, 'timeout') || str_contains($causeLower, 'connection')) {
            if ($metrics['db_latency'] > 400) {
                $boost += 0.1;
            }
        }
        
        return $boost;
    }
    
    private function getCorrelatedSignals(array $metrics, array $logSummary): array
    {
        $signals = [];
        
        // High latency + errors
        if ($metrics['db_latency'] > 300 && $logSummary['severity_breakdown']['error'] > 0) {
            $signals[] = [
                'signal' => 'High DB latency with error spike',
                'strength' => 'strong',
                'metrics' => [
                    'db_latency' => $metrics['db_latency'],
                    'error_count' => $logSummary['severity_breakdown']['error']
                ]
            ];
        }
        
        // High CPU + high requests
        if ($metrics['cpu_usage'] > 75 && $metrics['requests_per_sec'] > 500) {
            $signals[] = [
                'signal' => 'CPU saturation under load',
                'strength' => 'medium',
                'metrics' => [
                    'cpu_usage' => $metrics['cpu_usage'],
                    'requests_per_sec' => $metrics['requests_per_sec']
                ]
            ];
        }
        
        // Multiple time windows affected
        if ($logSummary['window_count'] > 3) {
            $signals[] = [
                'signal' => 'Sustained issue across multiple time windows',
                'strength' => 'medium',
                'metrics' => [
                    'window_count' => $logSummary['window_count']
                ]
            ];
        }
        
        return $signals;
    }
    
    private function generateNextSteps(array $cause, array $metrics): array
    {
        $steps = $cause['next_steps'] ?? [];
        $causeLower = strtolower($cause['cause']);
        
        // Add specific steps based on cause type
        if (str_contains($causeLower, 'database')) {
            $steps[] = 'Check database connection pool size';
            $steps[] = 'Review slow query log';
            $steps[] = 'Verify database server resources';
        }
        
        if (str_contains($causeLower, 'cpu')) {
            $steps[] = 'Profile application for CPU hotspots';
            $steps[] = 'Check for infinite loops or inefficient algorithms';
        }
        
        if (str_contains($causeLower, 'memory')) {
            $steps[] = 'Check for memory leaks';
            $steps[] = 'Review object caching strategy';
        }
        
        return array_unique($steps);
    }
    
    private function getDefaultCause(): array
    {
        return [
            'cause' => 'Unknown issue - requires manual investigation',
            'confidence' => 0.3,
            'reasoning' => 'Insufficient data to determine root cause',
            'evidence' => []
        ];
    }
}
