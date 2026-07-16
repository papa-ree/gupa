# Bale Gupa — IP Protection & Bot Detection

> IP blocking, rate limiting, dan bot detection untuk Bale landing pages.

## Requirements

- PHP ^8.3
- Laravel 11 / 12 / 13

## Installation

```bash
composer require bale/gupa
php artisan vendor:publish --tag=gupa:config
```

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

## License

MIT
