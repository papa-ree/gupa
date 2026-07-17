# Bale Gupa

> Intelligent IP protection, rate limiting, and bot detection for Laravel applications.

Gupa (from Sanskrit *gup* — to protect) is a comprehensive security layer that automatically scores incoming requests, blocks malicious actors, and provides real-time visibility into your application's threat landscape.

Part of the [Bale](https://github.com/bale) ecosystem. Works with any Laravel 11/12/13 project.

## Features

- **Behavioral Scoring Engine** — Multi-detector scoring system that evaluates every request in real-time
- **5 Built-in Detectors** — Velocity, honeypot, HTTP headers, 404 flooding, rate limiting
- **Automatic Blocking** — Temporary or permanent IP blocking with configurable thresholds
- **Dual Storage** — Cache-only (Redis/file) or persistent (database) mode
- **CIDR & Wildcard Support** — Whitelist/blacklist entire subnets or IP ranges
- **Dynamic Blacklist/Whitelist** — Add or remove IPs at runtime via Artisan
- **Suspicious Path Logging** — Logs request details when scores reach suspicious threshold
- **Notifications** — Webhook and email alerts when IPs are blocked
- **Dashboard & CLI** — Monitor, query, and manage everything from the command line

## Requirements

- PHP ^8.3
- Laravel 11, 12, or 13

## Installation

```bash
composer require bale/gupa
php artisan gupa:setup
```

The setup wizard will guide you through mode selection:

| Mode | What it does |
|------|-------------|
| **Auto** (default) | Publishes config and env vars. Cache-only storage. |
| **Advance** | Auto + database migrations + persistent storage. |

## Quick Start

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Bale\Gupa\Middleware\GuardianMiddleware::class);
})
```

Enable in `.env`:

```env
GUPA_ENABLED=true
```

Every request is now protected automatically.

## How It Works

```
Request → GuardianMiddleware → ScoreCalculator → Detectors → Score
                                                              │
                                            ┌─────────────────┤
                                            │                 │
                                     score < threshold    score >= threshold
                                            │                 │
                                         PASS            Block IP (403)
```

Each detector independently evaluates the request and contributes a score. When the total score reaches the threshold, the IP is blocked.

### Detectors

| Detector | What it catches | Default Score |
|----------|----------------|---------------|
| **Velocity** | Excessive request rate | 30 |
| **Honeypot** | Bot trap fields and hidden routes | 50 |
| **Header** | Missing standard browser headers, known bots | 20 |
| **NotFound** | Repeated 404 probing | 15 per hit |
| **RateLimit** | Requests exceeding rate limit | 25 |

## Configuration

All settings live in `config/gupa.php` and can be overridden via `.env`:

```env
# Core
GUPA_ENABLED=true
GUPA_THRESHOLD=100              # Total score to trigger block
GUPA_SCORE_DECAY_WINDOW=300     # Seconds before score resets (5 min)
GUPA_BLOCK_DURATION=3600        # Seconds for temporary block (1 hour)
GUPA_LOG_ENABLED=true

# Storage
GUPA_STORAGE=cache              # "cache" or "database"

# Suspicious logging (database mode only)
GUPA_SUSPICIOUS_THRESHOLD=10    # Log requests when score reaches this
GUPA_LOG_RETENTION_DAYS=30      # Auto-prune logs older than this

# Notifications
GUPA_NOTIFY_WEBHOOK=false
GUPA_NOTIFY_WEBHOOK_URL=
GUPA_NOTIFY_EMAIL=false
GUPA_NOTIFY_EMAIL_TO=
```

## Storage Modes

| Mode | Backend | Pros | Cons |
|------|---------|------|------|
| **cache** | Redis / file | Zero DB overhead, fast | Lost on cache flush |
| **database** | SQLite / MySQL / PostgreSQL | Persistent, queryable | Slight DB overhead |

### Whitelist & Blacklist

Static lists via config:

```php
// config/gupa.php
'whitelist' => [
    'enabled' => true,
    'ips' => ['127.0.0.1', '::1', '10.0.0.0/8'],
],
'blacklist' => [
    'enabled' => true,
    'ips' => ['192.168.1.*'],
],
```

Dynamic lists via Artisan:

```bash
# Blacklist — immediately blocks (whitelist auto-removed if exists)
php artisan gupa:blacklist --add=1.2.3.4
php artisan gupa:blacklist --add=10.0.0.0/8
php artisan gupa:blacklist --remove=1.2.3.4
php artisan gupa:blacklist --list

# Whitelist — bypasses all detection (blacklist auto-removed if exists)
php artisan gupa:whitelist --add=10.0.0.1
php artisan gupa:whitelist --remove=10.0.0.1
php artisan gupa:whitelist --list
```

> Adding to blacklist auto-removes from whitelist, and vice versa.

## Artisan Commands

```bash
# Monitoring
php artisan gupa:dashboard              # Real-time overview
php artisan gupa:dashboard --json       # JSON output
php artisan gupa:stats                  # Configuration summary

# IP Management
php artisan gupa:unblock 192.168.1.100  # Unblock a temporarily blocked IP
php artisan gupa:clear-score 192.168.1.100  # Reset score without unblocking

# Whitelist & Blacklist
php artisan gupa:whitelist --add=10.0.0.1
php artisan gupa:whitelist --remove=10.0.0.1
php artisan gupa:whitelist --list
php artisan gupa:blacklist --add=1.2.3.4
php artisan gupa:blacklist --remove=1.2.3.4
php artisan gupa:blacklist --list

# Logging (database mode)
php artisan gupa:log                        # Recent logs
php artisan gupa:log --ip=1.2.3.4           # Filter by IP
php artisan gupa:log --status=404           # Filter by status code
php artisan gupa:log --event=block          # Filter by event type
php artisan gupa:log --days=7               # Last N days
php artisan gupa:log --prune                # Delete old logs
php artisan gupa:log --json                 # JSON output
```

## Honeypot Component

Embed a hidden trap field in your forms:

```blade
<x-gupa::honeypot />

{{-- Custom field name and action --}}
<x-gupa::honeypot fieldName="email_backup" action="{{ route('contact.store') }}" />
```

Bots that fill the hidden field are scored automatically.

## Try It

### 1. Check status

```bash
php artisan gupa:dashboard
```

### 2. Simulate a bot

```bash
# Normal request — passes through
curl -I https://yourdomain.com

# Bot-like user agent — gets scored
curl -I -A "python-requests/2.28" https://yourdomain.com

# Missing standard headers — gets scored
curl -I -H "Accept:" -H "Accept-Language:" https://yourdomain.com
```

### 3. Check the score increase

```bash
php artisan gupa:dashboard --json
```

### 4. Force block yourself (for testing)

```bash
php artisan gupa:blacklist --add=YOUR_IP
# Open browser → 403 Forbidden

php artisan gupa:blacklist --remove=YOUR_IP
# Access restored
```

### 5. Check logs

```bash
tail -f storage/logs/laravel.log | grep Gupa
```

## Architecture

```
packages/bale-gupa/
├── config/gupa.php              # All configuration
├── database/migrations/         # 4 migrations (database mode)
├── resources/views/             # Honeypot Blade component
├── src/
│   ├── Actions/                 # BlockAction, LogAction, NotifyAction
│   ├── Commands/                # 8 Artisan commands
│   ├── Detectors/               # 5 detectors (Velocity, Honeypot, Header, NotFound, RateLimit)
│   ├── Middleware/               # GuardianMiddleware (entry point)
│   ├── Models/                  # BlockedIp, Log, Whitelist, Blacklist
│   ├── Notifications/           # Webhook & Email channels
│   ├── Scorer/                  # ScoreCalculator (orchestrator)
│   └── Support/                 # WhitelistChecker (CIDR, wildcard, exact match)
└── tests/                       # 118 tests (Pest)
```

## Testing

```bash
cd packages/bale-gupa
composer test
```

## License

MIT
