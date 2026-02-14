# Low Level Design - GET /api/analyses Endpoint

## Overview
This document describes the detailed design of the `GET /api/analyses` endpoint which retrieves and displays all log analyses with enhanced formatting.

---

## API Endpoint Details

```
Method: GET
URL: http://localhost:8000/api/analyses
Authentication: None (can be added)
Content-Type: application/json
```

---

## System Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT REQUEST                              │
│                  GET http://localhost:8000/api/analyses              │
│                  Query Params: ?status=completed&page=1              │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      LARAVEL ROUTER                                  │
│                      routes/api.php                                  │
│  Route::get('/analyses', [LogAnalysisController::class, 'index'])   │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│              LogAnalysisController::index()                          │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Step 1: Query Database                                       │  │
│  │  ├─ Load Analysis with relationships                          │  │
│  │  ├─ Filter by status (if provided)                            │  │
│  │  ├─ Order by created_at DESC                                  │  │
│  │  └─ Paginate (20 per page)                                    │  │
│  │                                                                │  │
│  │  Step 2: Transform Data                                       │  │
│  │  ├─ Calculate severity levels                                 │  │
│  │  ├─ Group error messages                                      │  │
│  │  ├─ Format timestamps                                         │  │
│  │  ├─ Extract RCA data                                          │  │
│  │  └─ Build readable response                                   │  │
│  │                                                                │  │
│  │  Step 3: Return Response                                      │  │
│  │  ├─ Check view_type parameter                                 │  │
│  │  ├─ Return HTML view OR JSON                                  │  │
│  │  └─ Include pagination metadata                               │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      DATABASE QUERIES                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Query 1: Fetch Analyses                                      │  │
│  │  SELECT * FROM analyses                                       │  │
│  │  WHERE status = ? (optional)                                  │  │
│  │  ORDER BY created_at DESC                                     │  │
│  │  LIMIT 20 OFFSET ?                                            │  │
│  │                                                                │  │
│  │  Query 2: Eager Load Log Entries                              │  │
│  │  SELECT * FROM log_entries                                    │  │
│  │  WHERE analysis_id IN (...)                                   │  │
│  │                                                                │  │
│  │  Query 3: Eager Load System Metrics                           │  │
│  │  SELECT * FROM system_metrics                                 │  │
│  │  WHERE analysis_id IN (...)                                   │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    DATA TRANSFORMATION                               │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  For Each Analysis:                                           │  │
│  │                                                                │  │
│  │  1. Calculate Problem Summary                                 │  │
│  │     ├─ Count errors by severity                               │  │
│  │     ├─ Determine severity level (🔴🟠🟡🟢)                     │  │
│  │     └─ Calculate confidence percentage                        │  │
│  │                                                                │  │
│  │  2. Process Error Messages                                    │  │
│  │     ├─ Group by unique message                                │  │
│  │     ├─ Count occurrences                                      │  │
│  │     ├─ Find first & last occurrence                           │  │
│  │     └─ Take top 10 errors                                     │  │
│  │                                                                │  │
│  │  3. Extract Sample Logs                                       │  │
│  │     ├─ Filter critical & error logs                           │  │
│  │     ├─ Take first 5 samples                                   │  │
│  │     └─ Format with timestamp                                  │  │
│  │                                                                │  │
│  │  4. Build Solution Section                                    │  │
│  │     ├─ Extract next_steps array                               │  │
│  │     ├─ Get reasoning text                                     │  │
│  │     └─ Include AI suggestions                                 │  │
│  │                                                                │  │
│  │  5. Extract RCA (Root Cause Analysis)                         │  │
│  │     ├─ Check ai_suggestions['rca']                            │  │
│  │     └─ Generate on-the-fly if missing                         │  │
│  │                                                                │  │
│  │  6. Format System Health                                      │  │
│  │     ├─ Get latest metric record                               │  │
│  │     └─ Format with units (%, ms)                              │  │
│  │                                                                │  │
│  │  7. Generate Quick Summary                                    │  │
│  │     └─ One-line description                                   │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         JSON RESPONSE                                │
│  {                                                                   │
│    "success": true,                                                  │
│    "data": [...],                                                    │
│    "pagination": {...}                                               │
│  }                                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Detailed Component Breakdown

### 1. Request Handler

```php
public function index(Request $request)
{
    // Step 1: Query with filters
    $analyses = Analysis::with(['logEntries', 'systemMetrics'])
        ->when($request->input('status'), fn($q, $status) => 
            $q->where('status', $status)
        )
        ->orderBy('created_at', 'desc')
        ->paginate(20);
    
    // Step 2: Transform data
    $transformed = $analyses->getCollection()->map(function ($analysis) {
        return $this->transformAnalysis($analysis);
    });
    
    // Step 3: Return response
    return response()->json([
        'success' => true,
        'data' => $transformed,
        'pagination' => $this->getPaginationMeta($analyses)
    ]);
}
```

### 2. Data Transformation Logic

```
transformAnalysis(Analysis $analysis)
    │
    ├─▶ calculateProblemSummary()
    │   ├─ Count errors by severity
    │   ├─ Determine severity level
    │   └─ Format confidence
    │
    ├─▶ processErrorMessages()
    │   ├─ Group by message
    │   ├─ Count occurrences
    │   └─ Find first/last seen
    │
    ├─▶ extractSampleLogs()
    │   ├─ Filter by severity
    │   └─ Take top 5
    │
    ├─▶ buildSolutionSection()
    │   ├─ Get next_steps
    │   ├─ Get reasoning
    │   └─ Get AI suggestions
    │
    ├─▶ extractRCA()
    │   ├─ Check ai_suggestions
    │   └─ Generate if missing
    │
    ├─▶ formatSystemHealth()
    │   └─ Get latest metrics
    │
    └─▶ generateQuickSummary()
        └─ Create one-liner
```

---

## Database Relationships

```
┌─────────────────────────┐
│      Analysis           │
│  (Main Record)          │
├─────────────────────────┤
│ id                      │
│ likely_cause            │
│ confidence              │
│ reasoning               │
│ next_steps (JSON)       │
│ ai_suggestions (JSON)   │
│ correlated_signals      │
│ status                  │
│ created_at              │
│ updated_at              │
└────────┬────────────────┘
         │
         │ hasMany
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌─────────────────┐    ┌─────────────────┐
│  LogEntry       │    │ SystemMetric    │
├─────────────────┤    ├─────────────────┤
│ id              │    │ id              │
│ analysis_id (FK)│    │ analysis_id (FK)│
│ log_timestamp   │    │ cpu_usage       │
│ severity        │    │ memory_usage    │
│ message         │    │ db_latency      │
│ raw_log         │    │ requests_per_sec│
│ is_duplicate    │    │ created_at      │
│ created_at      │    │ updated_at      │
│ updated_at      │    └─────────────────┘
└─────────────────┘
```

---

## Response Structure

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "timestamp": "2024-02-14 10:30:00",
      "status": "completed",
      
      "problem": {
        "title": "Multiple system failures detected",
        "severity": "🔴 CRITICAL",
        "confidence": "85%",
        "total_errors": 15,
        "critical_errors": 3,
        "warnings": 5
      },
      
      "errors": {
        "summary": "Found 10 unique error types",
        "messages": [
          {
            "message": "SQLSTATE[HY000] Connection refused",
            "severity": "error",
            "count": 2,
            "first_seen": "2024-02-14 10:01:23",
            "last_seen": "2024-02-14 10:02:15"
          }
        ],
        "sample_logs": [
          {
            "severity": "error",
            "message": "SQLSTATE[HY000] Connection refused",
            "timestamp": "2024-02-14 10:01:23",
            "raw": "[2024-02-14 10:01:23] production.ERROR: ..."
          }
        ]
      },
      
      "solution": {
        "next_steps": [
          "Check database connection pool",
          "Review slow queries",
          "Monitor memory usage"
        ],
        "reasoning": "Database connection issues, high CPU load",
        "ai_suggestions": {}
      },
      
      "rca": {
        "problem_statement": "System experiencing multiple failures",
        "root_cause": "Database connection pool exhaustion",
        "contributing_factors": [
          "High traffic load",
          "Insufficient connection pool size"
        ],
        "impact": {
          "severity": "critical",
          "affected_users": "all",
          "duration": "15 minutes"
        },
        "immediate_actions": [
          "Increase connection pool size",
          "Restart database connections"
        ],
        "long_term_solutions": [
          "Implement connection pooling",
          "Add database read replicas"
        ]
      },
      
      "system_health": {
        "cpu": "92.5%",
        "memory": "87.3%",
        "db_latency": "450ms",
        "requests_per_sec": 1500
      },
      
      "correlations": [
        "High DB latency correlates with error spike",
        "CPU usage increased during error period"
      ],
      
      "summary": "Critical issue detected: Multiple system failures (Confidence: 85%)"
    }
  ],
  
  "pagination": {
    "current_page": 1,
    "total": 50,
    "per_page": 20,
    "last_page": 3
  }
}
```

---

## Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| status | string | Filter by analysis status | ?status=completed |
| page | integer | Page number for pagination | ?page=2 |
| view_type | string | Response format (json/html) | ?view_type=html |

---

## Severity Level Calculation

```
getSeverityLevel(critical_count, error_count)
    │
    ├─ IF critical_count > 0
    │   └─ RETURN "🔴 CRITICAL"
    │
    ├─ ELSE IF error_count > 10
    │   └─ RETURN "🟠 HIGH"
    │
    ├─ ELSE IF error_count > 0
    │   └─ RETURN "🟡 MEDIUM"
    │
    └─ ELSE
        └─ RETURN "🟢 LOW"
```

---

## Error Message Grouping Logic

```
processErrorMessages(logEntries)
    │
    ├─ Filter logs by severity (error, critical)
    │
    ├─ Group by message text
    │   └─ For each group:
    │       ├─ Get first log (first occurrence)
    │       ├─ Get last log (last occurrence)
    │       ├─ Count total occurrences
    │       └─ Extract severity
    │
    ├─ Sort by count (descending)
    │
    └─ Take top 10 errors
```

---

## RCA Generation Flow

```
extractRCA(analysis)
    │
    ├─ Check if ai_suggestions['rca'] exists
    │   ├─ YES → Return existing RCA
    │   └─ NO → Generate on-the-fly
    │
    └─ generateRCAOnTheFly()
        │
        ├─ Build log summary
        │   ├─ Count total logs
        │   ├─ Breakdown by severity
        │   └─ Extract unique messages
        │
        ├─ Get system metrics
        │   ├─ CPU usage
        │   ├─ Memory usage
        │   ├─ DB latency
        │   └─ Requests per sec
        │
        ├─ Build top cause
        │   ├─ Use likely_cause
        │   ├─ Use confidence
        │   └─ Use reasoning
        │
        └─ Call DecisionEngine.decide()
            └─ Return generated RCA
```

---

## Performance Considerations

### 1. Database Optimization
```sql
-- Indexes for fast queries
CREATE INDEX idx_analyses_status ON analyses(status);
CREATE INDEX idx_analyses_created_at ON analyses(created_at);
CREATE INDEX idx_log_entries_analysis_id ON log_entries(analysis_id);
CREATE INDEX idx_log_entries_severity ON log_entries(severity);
CREATE INDEX idx_system_metrics_analysis_id ON system_metrics(analysis_id);
```

### 2. Eager Loading
```php
// Load relationships in single query
Analysis::with(['logEntries', 'systemMetrics'])
```

### 3. Pagination
```php
// Limit results to 20 per page
->paginate(20)
```

### 4. Query Optimization
```php
// Only load necessary columns
->select(['id', 'likely_cause', 'confidence', 'created_at'])
```

---

## Error Handling

```
index() Method
    │
    ├─ TRY
    │   ├─ Query database
    │   ├─ Transform data
    │   └─ Return response
    │
    └─ CATCH Exception
        └─ Return error response
            {
              "success": false,
              "error": "Failed to fetch analyses",
              "message": "..."
            }
```

---

## Example Usage

### Basic Request
```bash
curl http://localhost:8000/api/analyses
```

### With Status Filter
```bash
curl http://localhost:8000/api/analyses?status=completed
```

### With Pagination
```bash
curl http://localhost:8000/api/analyses?page=2
```

### HTML View
```bash
curl http://localhost:8000/api/analyses?view_type=html
```

---

## Testing Checklist

- [ ] Test without any data
- [ ] Test with single analysis
- [ ] Test with multiple analyses
- [ ] Test pagination (page 1, 2, 3)
- [ ] Test status filter (completed, processing, failed)
- [ ] Test with missing relationships
- [ ] Test with large dataset (100+ records)
- [ ] Test response time (should be < 500ms)
- [ ] Test HTML view rendering
- [ ] Test error handling

---

## Future Enhancements

1. **Caching**
   - Cache frequently accessed analyses
   - Cache transformed data
   - TTL: 5 minutes

2. **Search & Filters**
   - Search by error message
   - Filter by date range
   - Filter by confidence level

3. **Sorting**
   - Sort by confidence
   - Sort by error count
   - Sort by timestamp

4. **Export**
   - Export to CSV
   - Export to PDF
   - Export to Excel

5. **Real-time Updates**
   - WebSocket integration
   - Live analysis updates
   - Push notifications
