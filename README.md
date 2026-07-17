# Gupa

> Perlindungan IP, rate limiting, dan deteksi bot untuk aplikasi Laravel.

Gupa (dari Sansekerta *gup* — melindungi) adalah lapisan keamanan komprehensif yang secara otomatis memberi skor pada setiap request, memblokir aktor jahat, dan menyediakan visibilitas real-time terhadap ancaman di aplikasi Anda.

Bagian dari ekosistem [Bale](https://github.com/bale). Dapat digunakan di proyek Laravel 11/12/13 mana pun.

## Fitur

- **Mesin Skor Perilaku** — Sistem multi-detektor yang mengevaluasi setiap request secara real-time
- **5 Detektor Bawaan** — Velocity, honeypot, header HTTP, serangan 404, rate limiting
- **Pemblokiran Otomatis** — Blocking IP sementara atau permanen dengan threshold yang dapat dikonfigurasi
- **Dual Storage** — Mode cache-only (Redis/file) atau persistent (database)
- **Dukungan CIDR & Wildcard** — Whitelist/blacklist seluruh subnet atau range IP
- **Blacklist/Whitelist Dinamis** — Tambah atau hapus IP saat runtime melalui Artisan
- **Logging Path Mencurigakan** — Mencatat detail request saat skor mencapai threshold suspicious
- **Notifikasi** — Alert webhook dan email saat IP diblokir
- **Dashboard & CLI** — Pantau, query, dan kelola semuanya dari command line

## Persyaratan

- PHP ^8.3
- Laravel 11, 12, atau 13

## Instalasi

```bash
composer require bale/gupa
php artisan gupa:setup
```

Wizard setup akan memandu Anda dalam pemilihan mode:

| Mode | Yang dilakukan |
|------|---------------|
| **Auto** (default) | Publish config dan env vars. Storage cache-only. |
| **Advance** | Auto + migrasi database + persistent storage. |

## Mulai Cepat

Daftarkan middleware di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Bale\Gupa\Middleware\GuardianMiddleware::class);
})
```

Aktifkan di `.env`:

```env
GUPA_ENABLED=true
```

Setiap request sekarang terlindungi secara otomatis.

## Cara Kerja

```
Request → GuardianMiddleware → ScoreCalculator → Detectors → Skor
                                                              │
                                            ┌─────────────────┤
                                            │                 │
                                     skor < threshold    skor >= threshold
                                            │                 │
                                         PASS            Block IP (403)
```

Setiap detektor secara independen mengevaluasi request dan memberikan kontribusi skor. Ketika total skor mencapai threshold, IP diblokir.

### Detektor

| Detektor | Apa yang ditangkap | Skor Default |
|----------|-------------------|--------------|
| **Velocity** | Request rate berlebihan | 30 |
| **Honeypot** | Field jebakan bot dan rute tersembunyi | 50 |
| **Header** | Header browser standar hilang, bot yang dikenal | 20 |
| **NotFound** | Pencarian 404 berulang | 15 per hit |
| **RateLimit** | Request melebihi batas rate | 25 |

## Konfigurasi

Semua pengaturan ada di `config/gupa.php` dan dapat di-override melalui `.env`:

```env
# Inti
GUPA_ENABLED=true
GUPA_THRESHOLD=100              # Total skor untuk trigger block
GUPA_SCORE_DECAY_WINDOW=300     # Detik sebelum skor auto-reset (5 menit)
GUPA_BLOCK_DURATION=3600        # Detik durasi block sementara (1 jam)
GUPA_LOG_ENABLED=true

# Storage
GUPA_STORAGE=cache              # "cache" atau "database"

# Logging path mencurigakan (hanya database mode)
GUPA_SUSPICIOUS_THRESHOLD=10    # Log request saat skor mencapai ini
GUPA_LOG_RETENTION_DAYS=30      # Auto-prune log lebih lama dari ini

# Notifikasi
GUPA_NOTIFY_WEBHOOK=false
GUPA_NOTIFY_WEBHOOK_URL=
GUPA_NOTIFY_EMAIL=false
GUPA_NOTIFY_EMAIL_TO=
```

## Mode Storage

| Mode | Backend | Kelebihan | Kekurangan |
|------|---------|-----------|------------|
| **cache** | Redis / file | Tanpa beban DB, cepat | Hilang saat cache flush |
| **database** | SQLite / MySQL / PostgreSQL | Persistent, dapat di-query | Sedikit beban DB |

## Whitelist & Blacklist

List statis melalui config:

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

List dinamis melalui Artisan:

```bash
# Blacklist — langsung blokir (whitelist otomatis dihapus jika ada)
php artisan gupa:blacklist --add=1.2.3.4
php artisan gupa:blacklist --add=10.0.0.0/8
php artisan gupa:blacklist --remove=1.2.3.4
php artisan gupa:blacklist --list

# Whitelist — lewati semua deteksi (blacklist otomatis dihapus jika ada)
php artisan gupa:whitelist --add=10.0.0.1
php artisan gupa:whitelist --remove=10.0.0.1
php artisan gupa:whitelist --list
```

> Menambah ke blacklist otomatis menghapus dari whitelist, dan sebaliknya.

## Perintah Artisan

```bash
# Monitoring
php artisan gupa:dashboard              # Ikhtisar real-time
php artisan gupa:dashboard --json       # Output JSON
php artisan gupa:stats                  # Ringkasan konfigurasi

# Manajemen IP
php artisan gupa:unblock 192.168.1.100  # Buka blokir IP sementara
php artisan gupa:clear-score 192.168.1.100  # Reset skor tanpa unblock

# Whitelist & Blacklist
php artisan gupa:whitelist --add=10.0.0.1
php artisan gupa:whitelist --remove=10.0.0.1
php artisan gupa:whitelist --list
php artisan gupa:blacklist --add=1.2.3.4
php artisan gupa:blacklist --remove=1.2.3.4
php artisan gupa:blacklist --list

# Logging (database mode)
php artisan gupa:log                        # Log terbaru
php artisan gupa:log --ip=1.2.3.4           # Filter berdasarkan IP
php artisan gupa:log --status=404           # Filter berdasarkan status code
php artisan gupa:log --event=block          # Filter berdasarkan tipe event
php artisan gupa:log --days=7               # 7 hari terakhir
php artisan gupa:log --prune                # Hapus log lama
php artisan gupa:log --json                 # Output JSON
```

## Honeypot

Gupa mendeteksi bot melalui 3 cara: field tersembunyi, exact route match, dan prefix path.

### Field Tersembunyi

Sembunyikan field jebakan di form Anda:

```blade
<x-gupa::honeypot />

{{-- Custom field name --}}
<x-gupa::honeypot fieldName="email_backup" action="{{ route('contact.store') }}" />
```

Bot yang mengisi field tersembunyi akan mendapat skor 50.

### Routes (Exact Match)

Salah satu segment path harus sama persis. Cocok untuk halaman admin atau endpoint yang jarang diakses user normal.

```php
// config/gupa.php
'honeypot' => [
    'routes' => ['wp-login.php', 'xmlrpc.php'],
],
```

| Request | Terdeteksi? | Alasan |
|---------|-------------|--------|
| `/wp-login.php` | ✓ | segment `wp-login.php` match |
| `/2023/wp-login.php` | ✓ | segment `wp-login.php` match |
| `/sub/wp-login.php/x` | ✓ | segment `wp-login.php` match |
| `/wp-login` | ✗ | `wp-login` ≠ `wp-login.php` |
| `/login` | ✗ | tidak ada segment yang match |

### Prefixes (Awalan Segment)

Salah satu segment path harus diawali string tertentu. Cocok untuk memblokir seluruh grup path.

```php
// config/gupa.php
'honeypot' => [
    'prefixes' => ['wp-', 'wpv-'],
],
```

| Request | Terdeteksi? | Alasan |
|---------|-------------|--------|
| `/wp-admin.php` | ✓ | segment `wp-admin.php` diawali `wp-` |
| `/2023/wp-login.php` | ✓ | segment `wp-login.php` diawali `wp-` |
| `/wp-content/uploads/x` | ✓ | segment `wp-content` diawali `wp-` |
| `/wpv-view/123` | ✓ | segment `wpv-view` diawali `wpv-` |
| `/admin` | ✗ | tidak ada segment yang diawali `wp-` |
| `/login` | ✗ | tidak ada segment yang diawali `wp-` |

### Gabungan Routes + Prefixes

Keduanya dicek secara bersamaan, skor tetap sama.

```php
'honeypot' => [
    'routes' => ['xmlrpc.php', 'wp-login.php'],
    'prefixes' => ['wp-', 'wpv-'],
    'score' => 50,
],
```

Semua request ke path yang match akan mendapat skor 50, apakah dari `routes` maupun `prefixes`.

## Coba Sendiri

### 1. Cek status

```bash
php artisan gupa:dashboard
```

### 2. Simulasi bot

```bash
# Request normal — lolos
curl -I https://yourdomain.com

# User agent bot — kena skor
curl -I -A "python-requests/2.28" https://yourdomain.com

# Header standar hilang — kena skor
curl -I -H "Accept:" -H "Accept-Language:" https://yourdomain.com
```

### 3. Cek skor naik

```bash
php artisan gupa:dashboard --json
```

### 4. Force block diri sendiri (untuk testing)

```bash
php artisan gupa:blacklist --add=IP_ANDA
# Buka browser → 403 Forbidden

php artisan gupa:blacklist --remove=IP_ANDA
# Akses pulih
```

### 5. Cek log

```bash
tail -f storage/logs/laravel.log | grep Gupa
```

## Arsitektur

```
packages/bale-gupa/
├── config/gupa.php              # Semua konfigurasi
├── database/migrations/         # 4 migrasi (database mode)
├── resources/views/             # Komponen Blade honeypot
├── src/
│   ├── Actions/                 # BlockAction, LogAction, NotifyAction
│   ├── Commands/                # 8 perintah Artisan
│   ├── Detectors/               # 5 detektor (Velocity, Honeypot, Header, NotFound, RateLimit)
│   ├── Middleware/               # GuardianMiddleware (entry point)
│   ├── Models/                  # BlockedIp, Log, Whitelist, Blacklist
│   ├── Notifications/           # Channel Webhook & Email
│   ├── Scorer/                  # ScoreCalculator (orchestrator)
│   └── Support/                 # WhitelistChecker (CIDR, wildcard, exact match)
└── tests/                       # 118 tes (Pest)
```

## Pengujian

```bash
cd packages/bale-gupa
composer test
```

## Lisensi

MIT
