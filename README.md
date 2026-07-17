# Bale Gupa — IP Protection & Bot Detection

> IP blocking, rate limiting, dan bot detection untuk Bale landing pages.

## Requirements

- PHP ^8.3
- Laravel 11 / 12 / 13

## Installation

```bash
composer require bale/gupa
php artisan gupa:setup
```

### Mode Setup

**Auto (default)** — publish config + env vars minimal:

```
php artisan gupa:setup
? Pilih mode instalasi:
  > Auto (default)
```

**Advance** — auto + migrations + database storage:

```
php artisan gupa:setup
? Pilih mode instalasi:
  > Advance (database storage)
```

### Auto Mode

Publishes config + adds minimal env vars:

```
GUPA_ENABLED=true
GUPA_THRESHOLD=100
GUPA_SCORE_DECAY_WINDOW=300
GUPA_BLOCK_DURATION=3600
GUPA_LOG_ENABLED=true
```

### Advance Mode

Auto mode + migrations + database storage:

```
GUPA_ENABLED=true
GUPA_STORAGE=database
GUPA_THRESHOLD=100
...
```

Publishes 4 migrations:
- `gupa_blocked_ips` — IP blocking state
- `gupa_logs` — audit trail
- `gupa_whitelists` — dynamic whitelist
- `gupa_blacklists` — dynamic blacklist

## Quick Start

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(Bale\Gupa\Middleware\GuardianMiddleware::class);
})
```

```env
# .env
GUPA_ENABLED=true
```

Semua request otomatis diproteksi.

## Storage Modes

| Mode | Storage | Pros |
|------|---------|------|
| **cache** | Redis/file | Fast, zero DB overhead |
| **database** | SQLite/MySQL | Persistent, survives cache flush |

Switch via `GUPA_STORAGE=cache` or `GUPA_STORAGE=database`.

## Usage

### Honeypot Component

```blade
<x-gupa::honeypot />

{{-- Custom --}}
<x-gupa::honeypot fieldName="email_backup" action="{{ route('contact.store') }}" />
```

### Artisan Commands

```bash
php artisan gupa:dashboard              # Overview
php artisan gupa:stats                   # Configuration
php artisan gupa:unblock 192.168.1.100   # Unblock IP
php artisan gupa:clear-score 192.168.1.100
php artisan gupa:whitelist --add=10.0.0.0/8
php artisan gupa:blacklist --add=1.2.3.4
```

## Try It

### 1. Cek status

```bash
php artisan gupa:dashboard
```

### 2. Simulasi bot — kirim request dengan curl

```bash
# Normal request (akan lolos)
curl -I https://yourdomain.com

# Bot-like request (akan kena score)
curl -I -A "python-requests/2.28" https://yourdomain.com

# Bot dengan missing headers
curl -I -H "Accept:" -H "Accept-Language:" https://yourdomain.com
```

### 3. Cek score naik

```bash
php artisan gupa:dashboard --json
```

### 4. Force block IP sendiri (untuk testing)

```bash
# Tambah ke blacklist
php artisan gupa:blacklist --add=YOUR_IP

# Buka browser → akan dapat 403

# Hapus dari blacklist
php artisan gupa:blacklist --remove=YOUR_IP
```

### 5. Cek log

```bash
tail -f storage/logs/laravel.log | grep Gupa
```

## License

MIT
