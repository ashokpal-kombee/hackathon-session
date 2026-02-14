# Low Level Design (LLD) - Log Analysis API

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT REQUEST                              │
│                     POST /api/analyze                                │
│                     GET /api/analyses                                │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    LARAVEL ROUTING LAYER                             │
│                      routes/api.php                                  │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   LogAnalysisController                              │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  analyze()        - Main analysis endpoint                    │  │
│  │  index()          - List all analyses                         │  │
│  │  show()           - Get specific analysis                     │  │
│  │  analyzeFromFile()- Analyze from log files                    │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────┬──────────────────────────────────────────┬─────────────────┘
         │                                          │
         │ (1) Validate Input                       │ (Store/Retrieve)
         │                                          │
         ▼                                          ▼
┌─────────────────────┐                   ┌──────────────────────┐
│   Input Validation  │                   │   Database Layer     │
│  ┌───────────────┐  │                   │  ┌────────────────┐  │
│  │ logs[]        │  │                   │  │ analyses       │  │
│  │ metrics{}     │  │                   │  │ log_entries    │  │
│  │ timestamps    │  │                   │  │ system_metrics │  │
│  └───────────────┘  │                   │  └────────────────┘  │
└─────────┬───────────┘                   └──────────────────────┘
          │
          │ (2) Process Logs
          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      LogPreprocessor Service                         │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  process()                                                     │  │
│  │  ├─ Remove duplicates (hash-based)                            │  │
│  │  ├─ Detect severity (critical/error/warning/info)             │  │
│  │  ├─ Group by time windows (5-min intervals)                   │  │
│  │  └─ Extract patterns                                          │  │
│  │                                                                │  │
│  │  getSummary()                                                  │  │
│  │  ├─ Total logs count                                          │  │
│  │  ├─ Severity breakdown                                        │  │
│  │  ├─ Unique messages                                           │  │
│  │  └─ Time window count                                         │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────┬────────────────────────────────────────────────────────────┘
         │
         │ (3) AI Analysis
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     AIAnalysisService                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  analyze()                                                     │  │
│  │  ├─ Check API key availability                                │  │
│  │  ├─ Build structured prompt                                   │  │
│  │  ├─ Call AI API (OpenAI/Anthropic)                            │  │
│  │  ├─ Parse JSON response                                       │  │
│  │  └─ Fallback to rule-based if fails                           │  │
│  │                                                                │  │
│  │  buildPrompt()                                                 │  │
│  │  ├─ Format log summary                                        │  │
│  │  ├─ Include metrics                                           │  │
│  │  └─ Define JSON response structure                            │  │
│  │                                                                │  │
│  │  callAI()                                                      │  │
│  │  ├─ OpenAI API: chat/completions                              │  │
│  │  └─ Anthropic API: messages                                   │  │
│  │                                                                │  │
│  │  fallbackAnalysis()                                            │  │
│  │  ├─ Rule 1: DB latency + errors → DB issue                    │  │
│  │  ├─ Rule 2: High CPU → Resource exhaustion                    │  │
│  │  └─ Rule 3: Memory + errors → Memory leak                     │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────┬────────────────────────────────────────────────────────────┘
         │
         │ (4) Decision Making
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      DecisionEngine Service                          │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  decide()                                                      │  │
│  │  ├─ Correlate AI suggestions with metrics                     │  │
│  │  ├─ Adjust confidence scores                                  │  │
│  │  ├─ Rank probable causes                                      │  │
│  │  ├─ Generate actionable next steps                            │  │
│  │  └─ Build RCA (Root Cause Analysis)                           │  │
│  │                                                                │  │
│  │  correlateSignals()                                            │  │
│  │  ├─ DB latency + DB errors → +20% confidence                  │  │
│  │  ├─ High CPU + high load → +15% confidence                    │  │
│  │  ├─ Multiple time windows → +10% confidence                   │  │
│  │  └─ Critical errors → +25% confidence                         │  │
│  │                                                                │  │
│  │  buildRCA()                                                    │  │
│  │  ├─ Problem statement                                         │  │
│  │  ├─ Root cause                                                │  │
│  │  ├─ Contributing factors                                      │  │
│  │  ├─ Impact assessment                                         │  │
│  │  ├─ Immediate actions                                         │  │
│  │  └─ Long-term solutions                                       │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────┬────────────────────────────────────────────────────────────┘
         │
         │ (5) Store Results
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Database Models                               │
│  ┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐   │
│  │   Analysis       │  │   LogEntry       │  │  SystemMetric   │   │
│  ├──────────────────┤  ├──────────────────┤  ├─────────────────┤   │
│  │ id               │  │ id               │  │ id              │   │
│  │ likely_cause     │  │ analysis_id      │  │ analysis_id     │   │
│  │ confidence       │  │ log_timestamp    │  │ cpu_usage       │   │
│  │ reasoning        │  │ severity         │  │ memory_usage    │   │
│  │ next_steps       │  │ message          │  │ db_latency      │   │
│  │ ai_suggestions   │  │ raw_log          │  │ requests_per_sec│   │
│  │ correlated_sig   │  │ is_duplicate     │  │ created_at      │   │
│  │ status           │  │ created_at       │  │ updated_at      │   │
│  │ created_at       │  │ updated_at       │  └─────────────────┘   │
│  │ updated_at       │  └──────────────────┘                         │
│  └──────────────────┘                                               │
└────────┬────────────────────────────────────────────────────────────┘
         │
         │ (6) Return Response
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         JSON Response                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  {                                                             │  │
│  │    "analysis_id": 1,                                           │  │
│  │    "likely_cause": "Database overload",                        │  │
│  │    "confidence": 0.85,                                         │  │
│  │    "reasoning": "High DB latency + repeated errors",           │  │
│  │    "next_steps": ["Check connection pool", "Review queries"],  │  │
│  │    "correlated_signals": [...],                                │  │
│  │    "metadata": {...}                                           │  │
│  │  }                                                             │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Component Interaction Flow

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

## Data Flow Diagram

```
INPUT                    PROCESSING                      OUTPUT
─────                    ──────────                      ──────

┌─────────┐
│  Logs   │──┐
└─────────┘  │
             │         ┌──────────────┐
┌─────────┐  ├────────▶│ Preprocessor │
│ Metrics │──┘         └──────┬───────┘
└─────────┘                   │
                              │ Cleaned Data
                              ▼
                     ┌─────────────────┐
                     │   AI Service    │
                     │  (OpenAI GPT-4) │
                     └────────┬────────┘
                              │ AI Suggestions
                              ▼
                     ┌─────────────────┐
                     │ Decision Engine │
                     └────────┬────────┘
                              │ Final Decision
                              ▼
                     ┌─────────────────┐         ┌──────────────┐
                     │    Database     │────────▶│ JSON Response│
                     └─────────────────┘         └──────────────┘
```

---

## Class Diagram

```
┌─────────────────────────────────────┐
│   LogAnalysisController             │
├─────────────────────────────────────┤
│ - preprocessor: LogPreprocessor     │
│ - aiService: AIAnalysisService      │
│ - decisionEngine: DecisionEngine    │
│ - fileReader: LogFileReader         │
├─────────────────────────────────────┤
│ + analyze(Request): JsonResponse    │
│ + index(Request): JsonResponse      │
│ + show(int): JsonResponse           │
│ + analyzeFromFile(Request): Json    │
└─────────────────────────────────────┘
              │
              │ uses
              ▼
┌─────────────────────────────────────┐
│      LogPreprocessor                │
├─────────────────────────────────────┤
│ + process(array): array             │
│ + getSummary(array): array          │
│ - detectSeverity(string): string    │
│ - groupByTimeWindow(array): array   │
│ - removeDuplicates(array): array    │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│      AIAnalysisService              │
├─────────────────────────────────────┤
│ - apiKey: string                    │
│ - model: string                     │
├─────────────────────────────────────┤
│ + analyze(array, array): array      │
│ - buildPrompt(array, array): string │
│ - callAI(string): string            │
│ - parseAIResponse(string): array    │
│ - fallbackAnalysis(array): array    │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│      DecisionEngine                 │
├─────────────────────────────────────┤
│ + decide(array, array, array): array│
│ - correlateSignals(array): array    │
│ - adjustConfidence(float): float    │
│ - rankCauses(array): array          │
│ - buildRCA(array): array            │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│         Analysis (Model)            │
├─────────────────────────────────────┤
│ + id: int                           │
│ + likely_cause: string              │
│ + confidence: float                 │
│ + reasoning: text                   │
│ + next_steps: json                  │
│ + ai_suggestions: json              │
│ + correlated_signals: json          │
│ + status: string                    │
├─────────────────────────────────────┤
│ + logEntries(): HasMany             │
│ + systemMetrics(): HasMany          │
└─────────────────────────────────────┘
```

---

## Sequence Diagram

```
Client          Controller      Preprocessor    AIService    DecisionEngine    Database
  │                 │                │              │              │              │
  │─POST /analyze──▶│                │              │              │              │
  │                 │                │              │              │              │
  │                 │─validate()────▶│              │              │              │
  │                 │◀───────────────│              │              │              │
  │                 │                │              │              │              │
  │                 │─process()─────▶│              │              │              │
  │                 │◀──summary──────│              │              │              │
  │                 │                │              │              │              │
  │                 │─analyze()──────┼─────────────▶│              │              │
  │                 │                │              │              │              │
  │                 │                │              │─callAI()────▶│              │
  │                 │                │              │◀─response────│              │
  │                 │◀──suggestions──┼──────────────│              │              │
  │                 │                │              │              │              │
  │                 │─decide()───────┼──────────────┼─────────────▶│              │
  │                 │                │              │              │              │
  │                 │                │              │              │─correlate()─▶│
  │                 │◀──decision─────┼──────────────┼──────────────│              │
  │                 │                │              │              │              │
  │                 │─save()─────────┼──────────────┼──────────────┼─────────────▶│
  │                 │◀──stored───────┼──────────────┼──────────────┼──────────────│
  │                 │                │              │              │              │
  │◀─JSON response──│                │              │              │              │
  │                 │                │              │              │              │
```

---

## Database Schema

```
┌─────────────────────────────────────────────────────────────┐
│                        analyses                              │
├──────────────────┬──────────────────────────────────────────┤
│ id               │ BIGINT UNSIGNED PRIMARY KEY AUTO_INC     │
│ likely_cause     │ VARCHAR(255)                             │
│ confidence       │ DECIMAL(3,2)                             │
│ reasoning        │ TEXT                                     │
│ next_steps       │ JSON                                     │
│ ai_suggestions   │ JSON                                     │
│ correlated_sig   │ JSON                                     │
│ status           │ ENUM('processing','completed','failed')  │
│ created_at       │ TIMESTAMP                                │
│ updated_at       │ TIMESTAMP                                │
└──────────────────┴──────────────────────────────────────────┘
                              │
                              │ 1:N
                              │
        ┌─────────────────────┴─────────────────────┐
        │                                           │
        ▼                                           ▼
┌─────────────────────────┐           ┌─────────────────────────┐
│      log_entries        │           │    system_metrics       │
├─────────────────────────┤           ├─────────────────────────┤
│ id                      │           │ id                      │
│ analysis_id (FK)        │           │ analysis_id (FK)        │
│ log_timestamp           │           │ cpu_usage               │
│ severity                │           │ memory_usage            │
│ message                 │           │ db_latency              │
│ raw_log                 │           │ requests_per_sec        │
│ is_duplicate            │           │ additional_metrics      │
│ created_at              │           │ created_at              │
│ updated_at              │           │ updated_at              │
└─────────────────────────┘           └─────────────────────────┘
```

---

## API Request/Response Flow

### Request Example
```json
POST /api/analyze
{
  "logs": [
    {
      "message": "SQLSTATE[HY000] Connection refused",
      "timestamp": "2024-02-14 10:00:00"
    }
  ],
  "metrics": {
    "cpu_usage": 85,
    "memory_usage": 70,
    "db_latency": 450,
    "requests_per_sec": 1200
  }
}
```

### Processing Steps
```
1. Validation
   ├─ Check logs array exists
   ├─ Check metrics object exists
   └─ Validate data types

2. Preprocessing
   ├─ Remove duplicate logs
   ├─ Detect severity levels
   ├─ Group by time windows
   └─ Generate summary

3. AI Analysis
   ├─ Build structured prompt
   ├─ Call OpenAI API
   ├─ Parse JSON response
   └─ Fallback if fails

4. Decision Making
   ├─ Correlate with metrics
   ├─ Adjust confidence
   ├─ Rank causes
   └─ Generate next steps

5. Storage
   ├─ Save analysis
   ├─ Save log entries
   └─ Save metrics

6. Response
   └─ Return JSON
```

### Response Example
```json
{
  "analysis_id": 1,
  "likely_cause": "Database connection pool exhaustion",
  "confidence": 0.85,
  "reasoning": "High DB latency (450ms) with connection errors",
  "next_steps": [
    "Check database connection pool size",
    "Review slow query log",
    "Monitor database server resources"
  ],
  "correlated_signals": [
    "DB latency exceeds threshold",
    "Multiple connection errors detected"
  ],
  "metadata": {
    "logs_processed": 15,
    "unique_errors": 3,
    "time_windows": 2
  }
}
```

---

## Technology Stack Details

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
├─────────────────────────────────────────────────────────────┤
│  Framework: Laravel 12                                       │
│  Language: PHP 8.2                                           │
│  Architecture: MVC + Service Layer                           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    Service Layer                             │
├─────────────────────────────────────────────────────────────┤
│  LogPreprocessor    - Data cleaning & transformation         │
│  AIAnalysisService  - AI integration (OpenAI GPT-4)          │
│  DecisionEngine     - Business logic & correlation           │
│  LogFileReader      - File system operations                 │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    Data Layer                                │
├─────────────────────────────────────────────────────────────┤
│  Database: MySQL                                             │
│  ORM: Eloquent                                               │
│  Models: Analysis, LogEntry, SystemMetric                    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    External Services                         │
├─────────────────────────────────────────────────────────────┤
│  OpenAI API (GPT-4) - AI analysis                            │
│  Anthropic API (Claude) - Alternative AI provider            │
└─────────────────────────────────────────────────────────────┘
```
