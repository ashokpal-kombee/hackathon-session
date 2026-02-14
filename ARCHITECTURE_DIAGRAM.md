# Architecture Diagram - Log Analysis API

## System Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT REQUEST                               │
│                                                                      │
│  POST /api/analyze                                                   │
│  {                                                                   │
│    "logs": [...],                                                    │
│    "metrics": {...}                                                  │
│  }                                                                   │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    LogAnalysisController                             │
│                                                                      │
│  1. Validate input (logs + metrics)                                 │
│  2. Create Analysis record (status: processing)                     │
│  3. Orchestrate services                                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      LogPreprocessor                                 │
│                                                                      │
│  Input: Raw logs array                                              │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 1: Remove Duplicates                                     │  │
│  │   - MD5 hash comparison                                       │  │
│  │   - Mark is_duplicate flag                                    │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 2: Detect Severity                                       │  │
│  │   - error: "error", "failed", "exception"                     │  │
│  │   - warning: "warning", "warn"                                │  │
│  │   - critical: "critical", "fatal"                             │  │
│  │   - info: default                                             │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 3: Group by Time Windows                                 │  │
│  │   - 10-minute buckets                                         │  │
│  │   - Count windows affected                                    │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 4: Generate Summary                                      │  │
│  │   - Total logs                                                │  │
│  │   - Severity breakdown                                        │  │
│  │   - Unique messages only                                      │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  Output: Processed logs + summary                                   │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATABASE                                     │
│                                                                      │
│  Store processed logs in log_entries table                          │
│  Store metrics in system_metrics table                              │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      AIAnalysisService                               │
│                         (MCP Layer)                                  │
│                                                                      │
│  Input: Log summary + metrics                                       │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 1: Build Structured Prompt                               │  │
│  │   - Role: "You are a production incident analyzer"            │  │
│  │   - Context: Log summary, metrics                             │  │
│  │   - Task: Identify root cause with confidence                 │  │
│  │   - Format: JSON schema                                       │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 2: Call AI API                                           │  │
│  │   ┌────────────────────────────────────────────────────────┐ │  │
│  │   │ OpenAI API                                              │ │  │
│  │   │   POST https://api.openai.com/v1/chat/completions      │ │  │
│  │   │   Model: gpt-4                                          │ │  │
│  │   │   Temperature: 0.3 (deterministic)                      │ │  │
│  │   └────────────────────────────────────────────────────────┘ │  │
│  │   OR                                                           │  │
│  │   ┌────────────────────────────────────────────────────────┐ │  │
│  │   │ Anthropic API                                           │ │  │
│  │   │   POST https://api.anthropic.com/v1/messages           │ │  │
│  │   │   Model: claude-3-sonnet                                │ │  │
│  │   └────────────────────────────────────────────────────────┘ │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 3: Parse Response                                        │  │
│  │   - Extract JSON from response                                │  │
│  │   - Validate structure                                        │  │
│  │   - Handle parsing errors                                     │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 4: Fallback (if AI fails)                                │  │
│  │   - Rule 1: DB latency > 300 + errors → DB issue             │  │
│  │   - Rule 2: CPU > 80% → CPU exhaustion                        │  │
│  │   - Rule 3: Memory > 85% → Memory issue                       │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  Output: AI suggestions (probable_causes, next_steps)               │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       DecisionEngine                                 │
│                    (Backend Logic Layer)                             │
│                                                                      │
│  Input: AI suggestions + metrics + log summary                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 1: Enhance Causes                                        │  │
│  │   For each AI suggestion:                                     │  │
│  │   - Calculate correlation boost                               │  │
│  │   - Adjust confidence score                                   │  │
│  │                                                                │  │
│  │   Correlation Rules:                                          │  │
│  │   ┌────────────────────────────────────────────────────────┐ │  │
│  │   │ Database causes:                                        │ │  │
│  │   │   - DB latency > 300ms → +0.10 confidence              │ │  │
│  │   │   - DB latency > 500ms → +0.05 confidence              │ │  │
│  │   │   - Error count > 5 → +0.05 confidence                 │ │  │
│  │   └────────────────────────────────────────────────────────┘ │  │
│  │   ┌────────────────────────────────────────────────────────┐ │  │
│  │   │ CPU causes:                                             │ │  │
│  │   │   - CPU > 80% → +0.10 confidence                       │ │  │
│  │   │   - Requests/sec > 1000 → +0.05 confidence             │ │  │
│  │   └────────────────────────────────────────────────────────┘ │  │
│  │   ┌────────────────────────────────────────────────────────┐ │  │
│  │   │ Memory causes:                                          │ │  │
│  │   │   - Memory > 85% → +0.15 confidence                    │ │  │
│  │   └────────────────────────────────────────────────────────┘ │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 2: Rank Causes                                           │  │
│  │   - Sort by confidence (descending)                           │  │
│  │   - Select top cause                                          │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 3: Detect Correlated Signals                             │  │
│  │   - High latency + errors → "strong" signal                   │  │
│  │   - CPU saturation + high load → "medium" signal              │  │
│  │   - Multiple time windows → "medium" signal                   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Step 4: Generate Next Steps                                   │  │
│  │   - Add cause-specific actions                                │  │
│  │   - Combine with AI suggestions                               │  │
│  │   - Remove duplicates                                         │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  Output: Final decision with adjusted confidence                    │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATABASE                                     │
│                                                                      │
│  Update Analysis record:                                            │
│    - likely_cause                                                   │
│    - confidence (adjusted)                                          │
│    - reasoning                                                      │
│    - next_steps                                                     │
│    - ai_suggestions (original)                                      │
│    - correlated_signals                                             │
│    - status: completed                                              │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       FINAL RESPONSE                                 │
│                                                                      │
│  {                                                                   │
│    "analysis_id": 1,                                                 │
│    "likely_cause": "Database overload",                              │
│    "confidence": 0.82,                                               │
│    "reasoning": "High DB latency combined with timeout errors",      │
│    "next_steps": [                                                   │
│      "Check database connection pool size",                          │
│      "Review slow query log",                                        │
│      "Verify database server resources"                              │
│    ],                                                                │
│    "correlated_signals": [                                           │
│      {                                                               │
│        "signal": "High DB latency with error spike",                 │
│        "strength": "strong",                                         │
│        "metrics": {                                                  │
│          "db_latency": 400,                                          │
│          "error_count": 3                                            │
│        }                                                             │
│      }                                                               │
│    ],                                                                │
│    "metadata": {                                                     │
│      "logs_processed": 5,                                            │
│      "unique_errors": 3,                                             │
│      "time_windows": 1                                               │
│    }                                                                 │
│  }                                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Example

### Input
```json
{
  "logs": [
    {"message": "DB timeout", "timestamp": "12:00"},
    {"message": "DB timeout", "timestamp": "12:01"},  // Duplicate
    {"message": "DB connection reset", "timestamp": "12:02"}
  ],
  "metrics": {
    "cpu_usage": 85,
    "db_latency": 400,
    "requests_per_sec": 1200
  }
}
```

### After Preprocessing
```json
{
  "logs": [
    {"message": "DB timeout", "severity": "error", "is_duplicate": false},
    {"message": "DB timeout", "severity": "error", "is_duplicate": true},
    {"message": "DB connection reset", "severity": "error", "is_duplicate": false}
  ],
  "summary": {
    "total_logs": 3,
    "unique_messages": ["DB timeout", "DB connection reset"],
    "severity_breakdown": {"error": 3, "warning": 0},
    "window_count": 1
  }
}
```

### AI Prompt (Sent to MCP)
```
You are a production incident analyzer.

Log Summary:
- Total logs: 3
- Errors: 3
- Unique messages:
  1. DB timeout
  2. DB connection reset

System Metrics:
- CPU: 85%
- DB Latency: 400ms
- Requests/sec: 1200

Task: Identify root cause with confidence score (JSON format)
```

### AI Response
```json
{
  "probable_causes": [
    {
      "cause": "Database connection pool exhaustion",
      "confidence": 0.7,
      "reasoning": "Multiple timeout errors indicate connection issues",
      "evidence": ["DB timeout", "Connection reset"]
    }
  ],
  "next_steps": ["Check connection pool"],
  "correlations": ["Timeouts correlate with resets"]
}
```

### After Decision Engine
```json
{
  "likely_cause": "Database connection pool exhaustion",
  "confidence": 0.82,  // Boosted from 0.7 (+0.12)
  "reasoning": "Multiple timeout errors indicate connection issues",
  "next_steps": [
    "Check connection pool",
    "Check database connection pool size",  // Added by backend
    "Review slow query log",                // Added by backend
    "Verify database server resources"      // Added by backend
  ],
  "correlated_signals": [
    {
      "signal": "High DB latency with error spike",
      "strength": "strong",
      "metrics": {"db_latency": 400, "error_count": 3}
    }
  ]
}
```

---

## Component Interaction

```
┌──────────────────┐
│   Controller     │ ← Entry point
└────────┬─────────┘
         │
         ├─────────────────────────────────────┐
         │                                     │
         ▼                                     ▼
┌──────────────────┐                  ┌──────────────────┐
│  Preprocessor    │                  │    Database      │
│  - Deduplicate   │                  │  - Store logs    │
│  - Detect        │                  │  - Store metrics │
│  - Group         │                  │  - Store results │
│  - Summarize     │                  └──────────────────┘
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  AI Service      │ ← MCP Integration
│  - Build prompt  │
│  - Call API      │
│  - Parse JSON    │
│  - Fallback      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Decision Engine  │ ← Backend Logic
│  - Correlate     │
│  - Adjust conf.  │
│  - Rank causes   │
│  - Add steps     │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│    Response      │ ← Final output
└──────────────────┘
```

---

## Database Schema Relationships

```
┌─────────────────────────────────────┐
│           analyses                  │
│─────────────────────────────────────│
│ id (PK)                             │
│ likely_cause                        │
│ confidence                          │
│ reasoning                           │
│ next_steps (JSON)                   │
│ ai_suggestions (JSON)               │
│ correlated_signals (JSON)           │
│ status                              │
│ created_at                          │
│ updated_at                          │
└────────────┬────────────────────────┘
             │
             │ 1:N
             │
    ┌────────┴────────┐
    │                 │
    ▼                 ▼
┌─────────────┐  ┌──────────────────┐
│ log_entries │  │ system_metrics   │
│─────────────│  │──────────────────│
│ id (PK)     │  │ id (PK)          │
│ analysis_id │  │ analysis_id (FK) │
│ timestamp   │  │ cpu_usage        │
│ severity    │  │ memory_usage     │
│ message     │  │ db_latency       │
│ raw_log     │  │ requests_per_sec │
│ is_duplicate│  │ created_at       │
│ created_at  │  │ updated_at       │
│ updated_at  │  └──────────────────┘
└─────────────┘
```

---

## MCP Integration Points

### 1. Context Selection (Preprocessing)
- **Input:** 1000 raw logs
- **Output:** 50 unique logs
- **Reduction:** 95%

### 2. Structured Prompts (AI Service)
- **Format:** Role + Context + Task + Schema
- **Result:** Consistent JSON responses

### 3. Validation (Decision Engine)
- **Method:** Metric correlation
- **Result:** Adjusted confidence scores

### 4. Fallback (AI Service)
- **Trigger:** API failure or parsing error
- **Result:** Rule-based analysis

---

## Performance Characteristics

| Stage | Time | Notes |
|-------|------|-------|
| Preprocessing | ~50ms | In-memory operations |
| Database write | ~100ms | 3 tables, transactional |
| AI API call | ~2s | Network + AI processing |
| Decision engine | ~10ms | Pure computation |
| Database update | ~50ms | Single update |
| **Total** | **~2.2s** | With AI |
| **Fallback** | **~200ms** | Without AI |

---

## Error Handling Flow

```
┌─────────────┐
│   Request   │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Validate   │ ─── Invalid ──→ 422 Error
└──────┬──────┘
       │ Valid
       ▼
┌─────────────┐
│   Process   │ ─── Exception ──→ Rollback + 500 Error
└──────┬──────┘
       │ Success
       ▼
┌─────────────┐
│  AI Call    │ ─── Failure ──→ Fallback Analysis
└──────┬──────┘
       │ Success
       ▼
┌─────────────┐
│   Decide    │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Response   │ ──→ 200 Success
└─────────────┘
```

---

This architecture ensures:
- ✅ Efficient data processing
- ✅ Reliable AI integration
- ✅ Graceful error handling
- ✅ Scalable design
- ✅ Maintainable code structure
