# Bale Gupa — IP Protection & Bot Detection

> `bale/gupa` — IP blocking, rate limiting, dan bot detection untuk Bale landing pages.
> Modeled after [laravel-bot-guardian](https://github.com/febryntara/laravel-bot-guardian).

## Fitur

- **Score-Based IP Blocking** — Setiap request dianalisa, skor diakumulasikan, IP diblokir saat threshold tercapai
- **5 Built-in Detectors** — Velocity, Honeypot, Header, NotFound, Rate Limit — masing-masing configurable
- **Whitelist & Blacklist** — Exact IP, CIDR notation (`10.0.0.0/8`), dan wildcard (`192.168.*.*`)
- **Dynamic Whitelist/Blacklist** — Tambah/hapus via Artisan command tanpa restart
- **Rate Limiting** — Per-IP rate limit via Laravel's built-in `RateLimiter`
- **Notifications** — Webhook (HTTP POST) dan email saat IP diblokir/unblock
- **Recidivist Detection** — IP yang 3x diblokir dalam 24 jam otomatis di-block permanen
- **6 Artisan Commands** — Dashboard, stats, unblock, clear-score, whitelist, blacklist
- **93 Tests** — Pest, Orchestra Testbench, full coverage

## Requirements

- PHP ^8.3
- Laravel 11 / 12 / 13
- Redis (production) atau file cache (development)

## Installation

```bash
# 1. Tambah path repo di root composer.json
# (sudah dilakukan di bale-front)

# 2. Install package
composer require bale/gupa

# 3. Publish config (optional)
php artisan vendor:publish --tag=gupa:config
```

### Manual Registration (Bootstrap)

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(Bale\Gupa\Middleware\GuardianMiddleware::class);
})
```

## Quick Start

```php
// bootstrap/app.php — sudah terdaftar global
// Semua request otomatis melewati GuardianMiddleware
```

Request flow:

```
Request → Whitelist? → Blacklist? → Blocked? → Pending? → Rate Limit? → Process → Score → Block?
```

## Configuration

### Master

```php
'master' => [
    'enabled'            => env('GUPA_ENABLED', true),        // Global kill switch
    'threshold'          => env('GUPA_THRESHOLD', 100),        // Skor untuk trigger block
    'score_decay_window' => env('GUPA_SCORE_DECAY_WINDOW', 300), // Detik sebelum skor auto-reset
    'block_duration'     => env('GUPA_BLOCK_DURATION', 3600),  // Detik durasi block temporary
    'log_enabled'        => env('GUPA_LOG_ENABLED', true),     // Log ke Laravel log
],
```

### Whitelist

```php
'whitelist' => [
    'enabled' => env('GUPA_WHITELIST_ENABLED', true),
    'ips'     => ['127.0.0.1', '::1'],  // Mendukung exact, CIDR, wildcard
],
```

### Blacklist

```php
'blacklist' => [
    'enabled' => env('GUPA_BLACKLIST_ENABLED', false),
    'ips'     => [],  // Mendukung exact, CIDR, wildcard
],
```

### Detectors

```php
'detectors' => [
    'velocity' => [
        'enabled'      => env('GUPA_VELOCITY_ENABLED', true),
        'max_requests'  => env('GUPA_VELOCITY_MAX_REQUESTS', 60),  // Max request per window
        'window'        => env('GUPA_VELOCITY_WINDOW', 60),        // Detik
        'score'         => env('GUPA_VELOCITY_SCORE', 15),         // Skor tambahan
    ],

    'honeypot' => [
        'enabled'    => env('GUPA_HONEYPOT_ENABLED', true),
        'field_name'  => env('GUPA_HONEYPOT_FIELD', 'website_url'), // Hidden field
        'routes'      => [],  // Route tersembunyi
        'score'       => env('GUPA_HONEYPOT_SCORE', 50),
    ],

    'header' => [
        'enabled'                       => env('GUPA_HEADER_ENABLED', true),
        'bot_user_agent_score'          => env('GUPA_HEADER_BOT_UA_SCORE', 20),
        'missing_accept_score'          => env('GUPA_HEADER_MISSING_ACCEPT_SCORE', 10),
        'missing_accept_language_score' => env('GUPA_HEADER_MISSING_ACCEPT_LANG_SCORE', 10),
        'missing_referer_post_score'    => env('GUPA_HEADER_MISSING_REFERER_POST_SCORE', 5),
    ],

    'notfound' => [
        'enabled'  => env('GUPA_NOTFOUND_ENABLED', true),
        'max_404s' => env('GUPA_NOTFOUND_MAX_404S', 10),
        'window'   => env('GUPA_NOTFOUND_WINDOW', 60),
        'score'    => env('GUPA_NOTFOUND_SCORE', 20),
    ],

    'rate_limit' => [
        'enabled' => env('GUPA_RATE_LIMIT_DETECTOR_ENABLED', true),
    ],
],
```

### Rate Limits

```php
'rate_limits' => [
    'enabled' => env('GUPA_RATE_LIMITS_ENABLED', false),

    'default' => [
        'max_attempts'  => env('GUPA_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_seconds' => env('GUPA_RATE_LIMIT_DECAY_SECONDS', 60),
        'score'         => env('GUPA_RATE_LIMIT_SCORE', 10),
    ],
],
```

### Notifications

```php
'notifications' => [
    'enabled' => env('GUPA_NOTIFICATIONS_ENABLED', false),

    'channels' => [
        'webhook' => [
            'enabled' => env('GUPA_WEBHOOK_ENABLED', false),
            'url'     => env('GUPA_WEBHOOK_URL', ''),
            'secret'  => env('GUPA_WEBHOOK_SECRET', ''),  // Optional X-Gupa-Secret header
        ],

        'email' => [
            'enabled' => env('GUPA_EMAIL_ENABLED', false),
            'to'      => env('GUPA_EMAIL_TO', ''),
            'from'    => env('GUPA_EMAIL_FROM', ''),
        ],
    ],
],
```

## Detectors

### VelocityDetector

Mendeteksi request berlebih dalam waktu singkat. Counter per-IP di-reset setelah window expired.

| Config | Default | Description |
|--------|---------|-------------|
| `max_requests` | 60 | Max request per window |
| `window` | 60 | Durasi window (detik) |
| `score` | 15 | Skor tambahan |

### HoneypotDetector

Mendeteksi bot yang mengisi hidden field (`website_url`) atau mengakses route tersembunyi.

| Config | Default | Description |
|--------|---------|-------------|
| `field_name` | `website_url` | Nama hidden field |
| `routes` | `[]` | Route tersembunyi |
| `score` | 50 | Skor tambahan |

### HeaderDetector

Mendeteksi user-agent bot, header `Accept`/`Accept-Language` missing, dan POST tanpa `Referer`.

| Check | Score | Detection |
|-------|-------|-----------|
| Bot user-agent | 20 | `scrapy`, `curl`, `wget`, `python-*`, `go-http-client`, `php/`, `nikto`, `sqlmap`, `nmap`, dll |
| Empty user-agent | 20 | UA kosong = bot |
| Missing `Accept` | 10 | Browser selalu kirim header ini |
| Missing `Accept-Language` | 10 | Browser selalu kirim header ini |
| POST without `Referer` | 5 | Bot jarang punya referer |

### NotFoundDetector

Mendeteksi scanning behavior (excessive 404). Dicatat di `terminate()` phase.

| Config | Default | Description |
|--------|---------|-------------|
| `max_404s` | 10 | Max 404 per window |
| `window` | 60 | Durasi window (detik) |
| `score` | 20 | Skor tambahan |

### RateLimitDetector

Membaca state rate limiter. Jika IP melebihi `max_attempts`, skor ditambahkan.

| Config | Default | Description |
|--------|---------|-------------|
| (mengikuti `rate_limits.default`) | | |

## Scoring Flow

```
Request masuk
    ↓
Whitelist? → Yes → Skip semua
    ↓ No
Blacklist? → Yes → 403 Blocked
    ↓ No
Already Blocked? → Yes → 403 Blocked
    ↓ No
Pending Block? → Yes → Apply block → 403 Blocked
    ↓ No
Rate Limited? → Yes → 429 Rate Limited
    ↓ No
Process Request
    ↓
terminate() → Hitung skor dari semua detector
    ↓
Score > 0? → Increment total skor di cache
    ↓
Total ≥ threshold? → Execute block (temporary/permanent)
    ↓
Recidivist (≥3 blocks)? → Block permanent
```

## Cache Keys

| Key Pattern | TTL | Description |
|-------------|-----|-------------|
| `gupa:score:{ip}` | `score_decay_window` | Akumulasi skor per-IP |
| `gupa:blocked:{ip}` | `block_duration` | Flag block temporary |
| `gupa:permanent:{ip}` | forever | Flag block permanent |
| `gupa:pending_block:{ip}` | `block_duration` | Block tertunda (apply di request berikutnya) |
| `gupa:block_count:{ip}` | 24 jam | Jumlah block dalam 24 jam |
| `gupa:velocity:requests:{ip}` | window + 60 | Timestamp request untuk velocity |
| `gupa:notfound:{ip}` | window + 60 | Timestamp 404 untuk notfound |
| `gupa:honeypot:{ip}` | - | Honeypot tracking |
| `gupa:ratelimit:{ip}` | decay_seconds | Rate limiter counter |

## Artisan Commands

### Dashboard

```bash
php artisan gupa:dashboard           # Terminal overview
php artisan gupa:dashboard --json    # JSON output
```

Output: status aktif, jumlah blocked/pending/permanent IPs, active detectors.

### Stats

```bash
php artisan gupa:stats              # Konfigurasi saat ini
php artisan gupa:stats --json       # JSON output
```

### Unblock IP

```bash
php artisan gupa:unblock 192.168.1.100
```

### Clear Score

```bash
php artisan gupa:clear-score 192.168.1.100
```

### Whitelist Management

```bash
php artisan gupa:whitelist                          # List semua
php artisan gupa:whitelist --add=10.0.0.0/8         # Tambah CIDR
php artisan gupa:whitelist --remove=10.0.0.5        # Hapus IP
```

### Blacklist Management

```bash
php artisan gupa:blacklist                          # List semua
php artisan gupa:blacklist --add=1.2.3.4            # Tambah IP
php artisan gupa:blacklist --remove=1.2.3.4         # Hapus IP
```

## HTTP Responses

### 403 — Blocked

```json
{
    "error": "Access denied.",
    "message": "Your IP has been blocked due to suspicious activity."
}
```

### 429 — Rate Limited

```json
{
    "error": "Rate limit exceeded.",
    "message": "Too many requests. Please try again later.",
    "retry_after": 60
}
```

Header: `Retry-After: 60`

## Custom Detectors

Buat class yang implement `DetectorInterface`:

```php
<?php

namespace App\Gupa\Detectors;

use Bale\Gupa\Detectors\DetectorInterface;
use Illuminate\Http\Request;

class CustomDetector implements DetectorInterface
{
    public function detect(Request $request): int
    {
        // Analisa request, return skor (0 = aman)
        if ($this->isSuspicious($request)) {
            return 25;
        }

        return 0;
    }

    public function isEnabled(): bool
    {
        return config('gupa.detectors.custom.enabled', true);
    }

    public function getName(): string
    {
        return 'custom';
    }
}
```

Register di `AppServiceProvider`:

```php
use Bale\Gupa\Scorer\ScoreCalculator;
use App\Gupa\Detectors\CustomDetector;

public function boot(): void
{
    $calculator = app(ScoreCalculator::class);
    $calculator->register(new CustomDetector());
}
```

## Architecture

```
packages/bale-gupa/
├── config/gupa.php
├── src/
│   ├── Actions/
│   │   ├── BlockAction.php          # Cache-based block/unblock/pending/recidivist
│   │   ├── LogAction.php            # Wraps Log::warning/info/critical
│   │   └── NotifyAction.php         # Orchestrates notification channels
│   ├── Commands/
│   │   ├── BlacklistCommand.php     # gupa:blacklist
│   │   ├── ClearScoreCommand.php    # gupa:clear-score
│   │   ├── DashboardCommand.php     # gupa:dashboard
│   │   ├── StatsCommand.php         # gupa:stats
│   │   ├── UnblockCommand.php       # gupa:unblock
│   │   └── WhitelistCommand.php     # gupa:whitelist
│   ├── Detectors/
│   │   ├── DetectorInterface.php    # Contract: detect(), isEnabled(), getName()
│   │   ├── HeaderDetector.php       # Bot UA, missing headers (score: 20+10+10+5)
│   │   ├── HoneypotDetector.php     # Hidden field/route (score: 50)
│   │   ├── NotFoundDetector.php     # Excessive 404s (score: 20)
│   │   ├── RateLimitDetector.php    # Rate limit violation (score: 10)
│   │   └── VelocityDetector.php     # Request flood (score: 15)
│   ├── Middleware/
│   │   └── GuardianMiddleware.php   # handle() + terminate() flow
│   ├── Notifications/
│   │   ├── BlockNotification.php    # Value object: toArray(), toEmailSubject/Body()
│   │   └── Channels/
│   │       ├── EmailChannel.php     # Mail::raw() plain-text
│   │       └── WebhookChannel.php   # Http::post() with X-Gupa-Secret
│   ├── Scorer/
│   │   └── ScoreCalculator.php      # Orchestrates detectors, atomic scoring
│   ├── Support/
│   │   └── WhitelistChecker.php     # Exact + CIDR + wildcard matching
│   └── GupaServiceProvider.php      # Singletons, middleware, detectors, commands
└── tests/
    ├── Feature/
    │   └── GuardianMiddlewareTest.php
    ├── Unit/
    │   ├── BlockActionTest.php
    │   ├── CommandTest.php
    │   ├── HeaderDetectorTest.php
    │   ├── HoneypotDetectorTest.php
    │   ├── NotFoundDetectorTest.php
    │   ├── NotifyActionTest.php
    │   ├── RateLimitDetectorTest.php
    │   ├── ScoreCalculatorTest.php
    │   ├── VelocityDetectorTest.php
    │   └── WhitelistCheckerTest.php
    ├── Pest.php
    └── TestCase.php
```

## Testing

```bash
cd packages/bale-gupa
composer test                    # Run all 93 tests
composer test -- --filter=Velocity  # Run specific test group
composer test-coverage           # With coverage report
```

## Score Reference

| Detector | Trigger | Score |
|----------|---------|-------|
| Velocity | >60 requests/60s | 15 |
| Honeypot | Hidden field filled | 50 |
| Honeypot | Honeypot route accessed | 50 |
| Header | Bot user-agent | 20 |
| Header | Empty user-agent | 20 |
| Header | Missing Accept | 10 |
| Header | Missing Accept-Language | 10 |
| Header | POST without Referer | 5 |
| NotFound | >10 x 404/60s | 20 |
| Rate Limit | Exceeded max_attempts | 10 |

**Threshold default: 100** — Artinya bot perlu melakukan ~4-5 pelanggaran berbeda sebelum diblokir.

## Environment Variables

```env
# Master
GUPA_ENABLED=true
GUPA_THRESHOLD=100
GUPA_SCORE_DECAY_WINDOW=300
GUPA_BLOCK_DURATION=3600
GUPA_LOG_ENABLED=true

# Whitelist
GUPA_WHITELIST_ENABLED=true

# Blacklist
GUPA_BLACKLIST_ENABLED=false

# Velocity
GUPA_VELOCITY_ENABLED=true
GUPA_VELOCITY_MAX_REQUESTS=60
GUPA_VELOCITY_WINDOW=60
GUPA_VELOCITY_SCORE=15

# Honeypot
GUPA_HONEYPOT_ENABLED=true
GUPA_HONEYPOT_FIELD=website_url
GUPA_HONEYPOT_SCORE=50

# Header
GUPA_HEADER_ENABLED=true
GUPA_HEADER_BOT_UA_SCORE=20
GUPA_HEADER_MISSING_ACCEPT_SCORE=10
GUPA_HEADER_MISSING_ACCEPT_LANG_SCORE=10
GUPA_HEADER_MISSING_REFERER_POST_SCORE=5

# Not Found
GUPA_NOTFOUND_ENABLED=true
GUPA_NOTFOUND_MAX_404S=10
GUPA_NOTFOUND_WINDOW=60
GUPA_NOTFOUND_SCORE=20

# Rate Limit
GUPA_RATE_LIMIT_DETECTOR_ENABLED=true
GUPA_RATE_LIMITS_ENABLED=false
GUPA_RATE_LIMIT_MAX_ATTEMPTS=60
GUPA_RATE_LIMIT_DECAY_SECONDS=60
GUPA_RATE_LIMIT_SCORE=10

# Notifications
GUPA_NOTIFICATIONS_ENABLED=false
GUPA_WEBHOOK_ENABLED=false
GUPA_WEBHOOK_URL=
GUPA_WEBHOOK_SECRET=
GUPA_EMAIL_ENABLED=false
GUPA_EMAIL_TO=
GUPA_EMAIL_FROM=
```

## License

MIT
