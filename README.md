# Log Analysis API - Step by Step Setup Guide

> AI-powered log analysis system for analyzing production errors and finding root causes

[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://php.net)

---

## 📋 Prerequisites

Before starting, make sure you have:
- PHP 8.2 or higher
- Composer
- MySQL server (running)

---

## 🚀 Step-by-Step Setup

### Step 1: Clone or Download the Project
```bash
cd your-project-folder
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Setup Environment File
```bash
# Copy the example environment file
copy .env.example .env

# Generate application key
php artisan key:generate
```

### Step 4: Configure Database
The project uses MySQL. Update your `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Make sure MySQL is running and the database exists:
```bash
# Create database (run in MySQL)
CREATE DATABASE your_database_name;
```

### Step 5: Run Migrations
```bash
php artisan migrate
```

This creates the following tables:
- `analyses` - Stores analysis results
- `log_entries` - Stores individual log entries
- `system_metrics` - Stores system health metrics

### Step 6: Seed Sample Data
```bash
php artisan db:seed --class=ErrorLogSeeder
```

This creates realistic error logs for testing, including:
- Database connection errors
- Memory exhaustion errors
- Timeout errors
- HTTP errors
- And more...

### Step 7: Start the Server
```bash
php artisan serve
```

Server will start at: `http://localhost:8000`

---

## 🎯 Using the API

### Available Endpoint

**GET /api/analyses** - View all analyzed logs

### Test the API

#### Option 1: Browser
Open your browser and visit:
```
http://localhost:8000/api/analyses
```

#### Option 2: cURL
```bash
curl http://localhost:8000/api/analyses
```

#### Option 3: Postman
1. Open Postman
2. Create a new GET request
3. URL: `http://localhost:8000/api/analyses`
4. Click Send

---

## 📊 What You'll See

The API returns detailed analysis including:

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
        "critical_errors": 3
      },
      "errors": {
        "summary": "Found 10 unique error types",
        "messages": [
          {
            "message": "SQLSTATE[HY000] [2002] Connection refused",
            "severity": "error",
            "count": 2,
            "first_seen": "2024-02-14 10:01:23",
            "last_seen": "2024-02-14 10:02:15"
          }
        ]
      },
      "solution": {
        "next_steps": [
          "Check database connection pool",
          "Review slow queries",
          "Monitor memory usage"
        ],
        "reasoning": "Database connection issues, high CPU load, and memory exhaustion"
      },
      "system_health": {
        "cpu": "92.5%",
        "memory": "87.3%",
        "db_latency": "450ms",
        "requests_per_sec": 1500
      }
    }
  ]
}
```

---

## 🔍 Understanding the Response

### Problem Section
- **title**: What went wrong
- **severity**: How critical (🔴 CRITICAL, 🟠 HIGH, 🟡 MEDIUM, 🟢 LOW)
- **confidence**: How sure the system is (0-100%)
- **total_errors**: Number of error logs found
- **critical_errors**: Number of critical errors

### Errors Section
- **summary**: Quick overview of unique errors
- **messages**: List of actual error messages with:
  - Message text
  - Severity level
  - How many times it occurred
  - When first and last seen

### Solution Section
- **next_steps**: What to do to fix the problem
- **reasoning**: Why the system thinks this is the cause

### System Health
- **cpu**: CPU usage percentage
- **memory**: Memory usage percentage
- **db_latency**: Database response time
- **requests_per_sec**: Traffic load

---

## 🏗️ Architecture & Design

### API Flow Diagram
```
GET /api/analyses
    │
    ├─▶ Query Database (with filters)
    │   ├─ Load Analysis records
    │   ├─ Eager load LogEntries
    │   └─ Eager load SystemMetrics
    │
    ├─▶ Transform Data
    │   ├─ Calculate severity levels
    │   ├─ Group error messages
    │   ├─ Extract RCA
    │   └─ Format response
    │
    └─▶ Return JSON Response
        ├─ Problem summary
        ├─ Error details
        ├─ Solution steps
        ├─ System health
        └─ Pagination
```

**Detailed Design:** [API_ANALYSES_LLD.md](API_ANALYSES_LLD.md) - Complete Low Level Design with:
- System flow diagram
- Database relationships
- Response structure
- Query parameters
- Performance optimization
- Error handling

---

## 📁 Project Structure

```
app/
├── Http/Controllers/Api/
│   └── LogAnalysisController.php    # Handles API requests
├── Models/
│   ├── Analysis.php                 # Analysis results model
│   ├── LogEntry.php                 # Log entries model
│   └── SystemMetric.php             # System metrics model
└── Services/
    ├── LogPreprocessor.php          # Cleans and processes logs
    ├── AIAnalysisService.php        # AI analysis (optional)
    └── DecisionEngine.php           # Makes final decisions

database/
├── migrations/                       # Database structure
└── seeders/
    └── ErrorLogSeeder.php           # Sample data generator

routes/
└── api.php                          # API routes definition
```

---

## 🛠️ Common Commands

```bash
# View all routes
php artisan route:list

# Clear cache
php artisan cache:clear

# Reset database and reseed
php artisan migrate:fresh --seed

# View logs
type storage\logs\laravel.log
```

---

## 🐛 Troubleshooting

### Database not found
```bash
# Make sure MySQL is running and database exists
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE your_database_name;

# Exit MySQL
exit;

# Run migrations again
php artisan migrate
```

### Port already in use
```bash
# Use a different port
php artisan serve --port=8001
```

### No data showing
```bash
# Reseed the database
php artisan db:seed --class=ErrorLogSeeder
```

### Routes not working
```bash
# Clear route cache
php artisan route:clear
php artisan config:clear
```

---

## 📝 Next Steps

1. ✅ You've set up the project
2. ✅ You've seeded sample data
3. ✅ You can view analyses via API

Want to analyze your own logs? Check out the other endpoints:
- `POST /api/analyze` - Analyze custom logs
- `GET /api/analysis/{id}` - Get specific analysis

---

## 💡 Key Features

- View all analyzed error logs
- See error patterns and frequencies
- Get actionable solutions
- Monitor system health metrics
- Track error severity levels

---

## 📞 Need Help?

If you encounter issues:
1. Check the troubleshooting section above
2. Review `storage/logs/laravel.log` for errors
3. Make sure all migrations ran successfully

---

**That's it! Your log analysis API is ready to use.** 🎉
