<?php
// includes/config.php — Meta Conversions API credentials

// Sri Lanka Standard Time (UTC+5:30) for every entry point that reaches CAPI
// without touching the database layer (e.g. api/track_pageview.php).
date_default_timezone_set('Asia/Colombo');
define('META_PIXEL_ID', '864545903278735');
define('META_ACCESS_TOKEN', 'EAAWVlRyDq7oBSQ10cXrywgBX6rCa9zXrBpREiyX6XgIw5bqosZBezTJromeWwFytPHcTuY4JiZCBE629jw0xOreSUj2qGXa4Pm2xFHjYIyadv8n9FQvRV0VxVe8KIJ8ThckCh8XiuRK7iZAZBzL1zZAWZBKQybpwExAJ2ffReqxFCfRZC2u0jw1XknQSkaZCpugYDQZDZD');
define('META_API_VERSION', 'v19.0');
define('META_TEST_EVENT_CODE', '');
