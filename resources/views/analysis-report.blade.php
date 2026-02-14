<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Analysis Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.1em; opacity: 0.9; }
        .content { padding: 30px; }
        .analysis-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .severity-critical { border-left-color: #dc3545; background: #fff5f5; }
        .severity-high { border-left-color: #fd7e14; background: #fff8f0; }
        .severity-medium { border-left-color: #ffc107; background: #fffbf0; }
        .severity-low { border-left-color: #28a745; background: #f0fff4; }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-right: 8px;
        }
        .badge-critical { background: #dc3545; color: white; }
        .badge-high { background: #fd7e14; color: white; }
        .badge-medium { background: #ffc107; color: #333; }
        .badge-low { background: #28a745; color: white; }
        .badge-error { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .section-title {
            font-size: 1.5em;
            margin: 25px 0 15px 0;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover { background: #f8f9fa; }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .metric-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .next-steps {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .next-steps li {
            margin: 8px 0;
            padding-left: 10px;
        }
        .error-log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        .timestamp {
            color: #6c757d;
            font-size: 0.85em;
        }
        .confidence-bar {
            height: 25px;
            background: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            margin: 10px 0;
        }
        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9em;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination span { background: #764ba2; }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Log Analysis Report</h1>
            <p>Comprehensive System Analysis Dashboard</p>
        </div>

        <div class="content">
            @if(isset($data) && count($data) > 0)
                @foreach($data as $analysis)
                    <div class="analysis-card severity-{{ strtolower(str_replace(['🔴 ', '🟠 ', '🟡 ', '🟢 '], '', $analysis['problem']['severity'])) }}">
                        
                        <!-- Header Section -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h2 style="color: #333; margin-bottom: 5px;">Analysis #{{ $analysis['id'] }}</h2>
                                <span class="timestamp">{{ $analysis['timestamp'] }}</span>
                            </div>
                            <div>
                                <span class="badge badge-{{ strtolower(str_replace(['🔴 ', '🟠 ', '🟡 ', '🟢 '], '', $analysis['problem']['severity'])) }}">
                                    {{ $analysis['problem']['severity'] }}
                                </span>
                                <span class="badge" style="background: #6c757d; color: white;">{{ $analysis['status'] }}</span>
                            </div>
                        </div>

                        <!-- Quick Summary -->
                        <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <h3 style="color: #667eea; margin-bottom: 10px;">📝 Quick Summary</h3>
                            <p style="font-size: 1.1em; line-height: 1.6;">{{ $analysis['summary'] }}</p>
                        </div>

                        <!-- Problem Details -->
                        <h3 class="section-title">🔴 Problem Details</h3>
                        <div style="background: white; padding: 15px; border-radius: 6px;">
                            <p style="font-size: 1.2em; font-weight: bold; margin-bottom: 10px;">{{ $analysis['problem']['title'] }}</p>
                            
                            <div class="confidence-bar">
                                <div class="confidence-fill" style="width: {{ $analysis['problem']['confidence'] }}">
                                    Confidence: {{ $analysis['problem']['confidence'] }}
                                </div>
                            </div>

                            <div class="metric-grid">
                                <div class="metric-box">
                                    <div class="metric-label">Total Errors</div>
                                    <div class="metric-value" style="color: #dc3545;">{{ $analysis['problem']['total_errors'] }}</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-label">Critical</div>
                                    <div class="metric-value" style="color: #dc3545;">{{ $analysis['problem']['critical_errors'] }}</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-label">Warnings</div>
                                    <div class="metric-value" style="color: #ffc107;">{{ $analysis['problem']['warnings'] }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Error Messages -->
                        @if(isset($analysis['errors']['messages']) && count($analysis['errors']['messages']) > 0)
                            <h3 class="section-title">⚠️ Error Messages</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Severity</th>
                                        <th>Message</th>
                                        <th>Count</th>
                                        <th>First Seen</th>
                                        <th>Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($analysis['errors']['messages'] as $error)
                                        <tr>
                                            <td>
                                                <span class="badge badge-{{ strtolower($error['severity']) }}">
                                                    {{ strtoupper($error['severity']) }}
                                                </span>
                                            </td>
                                            <td>{{ $error['message'] }}</td>
                                            <td><strong>{{ $error['count'] }}</strong></td>
                                            <td class="timestamp">{{ $error['first_seen'] }}</td>
                                            <td class="timestamp">{{ $error['last_seen'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <!-- Sample Logs -->
                        @if(isset($analysis['errors']['sample_logs']) && count($analysis['errors']['sample_logs']) > 0)
                            <h3 class="section-title">📋 Sample Error Logs</h3>
                            @foreach($analysis['errors']['sample_logs'] as $log)
                                <div class="error-log">
                                    <div style="margin-bottom: 5px;">
                                        <span class="badge badge-{{ strtolower($log['severity']) }}">{{ $log['severity'] }}</span>
                                        <span class="timestamp">{{ $log['timestamp'] }}</span>
                                    </div>
                                    <div><strong>Message:</strong> {{ $log['message'] }}</div>
                                    @if($log['raw'])
                                        <div style="margin-top: 5px; color: #6c757d;"><strong>Raw:</strong> {{ $log['raw'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @endif

                        <!-- Solution -->
                        <h3 class="section-title">💡 Recommended Solution</h3>
                        <div style="background: white; padding: 15px; border-radius: 6px;">
                            @if(isset($analysis['solution']['reasoning']))
                                <p style="margin-bottom: 15px;"><strong>Reasoning:</strong> {{ is_array($analysis['solution']['reasoning']) ? json_encode($analysis['solution']['reasoning']) : $analysis['solution']['reasoning'] }}</p>
                            @endif
                            
                            @if(isset($analysis['solution']['next_steps']) && count($analysis['solution']['next_steps']) > 0)
                                <div class="next-steps">
                                    <strong>Next Steps:</strong>
                                    <ol>
                                        @foreach($analysis['solution']['next_steps'] as $step)
                                            <li>{{ $step }}</li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif
                        </div>

                        <!-- Root Cause Analysis -->
                        @if(isset($analysis['rca']))
                            <h3 class="section-title">🔍 Root Cause Analysis (RCA)</h3>
                            <div style="background: white; padding: 15px; border-radius: 6px;">
                                @if(isset($analysis['rca']['primary_cause']))
                                    <div style="margin-bottom: 15px;">
                                        <strong>Primary Cause:</strong> {{ is_array($analysis['rca']['primary_cause']['cause']) ? json_encode($analysis['rca']['primary_cause']['cause']) : $analysis['rca']['primary_cause']['cause'] }}
                                        <div class="confidence-bar" style="margin-top: 5px;">
                                            <div class="confidence-fill" style="width: {{ round($analysis['rca']['primary_cause']['confidence'] * 100) }}%">
                                                {{ round($analysis['rca']['primary_cause']['confidence'] * 100) }}%
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if(isset($analysis['rca']['contributing_factors']) && count($analysis['rca']['contributing_factors']) > 0)
                                    <strong>Contributing Factors:</strong>
                                    <ul style="margin-top: 10px;">
                                        @foreach($analysis['rca']['contributing_factors'] as $factor)
                                            <li style="margin: 5px 0;">{{ $factor }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if(isset($analysis['rca']['timeline']) && count($analysis['rca']['timeline']) > 0)
                                    <div style="margin-top: 15px;">
                                        <strong>Timeline:</strong>
                                        <ul style="margin-top: 10px;">
                                            @foreach($analysis['rca']['timeline'] as $event)
                                                <li style="margin: 5px 0;">{{ $event }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- System Health -->
                        @if(isset($analysis['system_health']) && $analysis['system_health'])
                            <h3 class="section-title">📊 System Health Metrics</h3>
                            <div class="metric-grid">
                                <div class="metric-box">
                                    <div class="metric-label">CPU Usage</div>
                                    <div class="metric-value">{{ $analysis['system_health']['cpu'] }}</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-label">Memory Usage</div>
                                    <div class="metric-value">{{ $analysis['system_health']['memory'] }}</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-label">DB Latency</div>
                                    <div class="metric-value">{{ $analysis['system_health']['db_latency'] }}</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-label">Requests/Sec</div>
                                    <div class="metric-value">{{ $analysis['system_health']['requests_per_sec'] }}</div>
                                </div>
                            </div>
                        @endif

                        <!-- Correlations -->
                        @if(isset($analysis['correlations']) && count($analysis['correlations']) > 0)
                            <h3 class="section-title">🔗 Correlated Signals</h3>
                            <ul style="background: white; padding: 15px; border-radius: 6px;">
                                @foreach($analysis['correlations'] as $correlation)
                                    <li style="margin: 8px 0;">{{ $correlation }}</li>
                                @endforeach
                            </ul>
                        @endif

                    </div>
                @endforeach

                <!-- Pagination -->
                @if(isset($pagination))
                    <div class="pagination">
                        @if($pagination['current_page'] > 1)
                            <a href="?view_type=html&page={{ $pagination['current_page'] - 1 }}">← Previous</a>
                        @endif
                        
                        <span>Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}</span>
                        
                        @if($pagination['current_page'] < $pagination['last_page'])
                            <a href="?view_type=html&page={{ $pagination['current_page'] + 1 }}">Next →</a>
                        @endif
                    </div>
                @endif

            @else
                <div style="text-align: center; padding: 50px;">
                    <h2 style="color: #6c757d;">No analyses found</h2>
                    <p style="color: #adb5bd;">Start by analyzing some logs!</p>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Generated on {{ date('Y-m-d H:i:s') }} | Log Analysis System v1.0</p>
        </div>
    </div>
</body>
</html>
