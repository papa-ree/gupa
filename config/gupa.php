<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master
    |--------------------------------------------------------------------------
    |
    | Global kill switch dan parameter inti scoring.
    |
    | enabled            — Aktifkan/nonaktifkan Gupa secara global
    | threshold          — Total skor untuk trigger block (default: 100)
    | score_decay_window — Detik sebelum skor auto-reset (default: 300 = 5 menit)
    | block_duration     — Detik durasi block temporary (default: 3600 = 1 jam)
    | log_enabled        — Log block/unblock events ke Laravel log
    |
    */

    'master' => [
        'enabled' => env('GUPA_ENABLED', true),
        'threshold' => (int) env('GUPA_THRESHOLD', 100),
        'score_decay_window' => (int) env('GUPA_SCORE_DECAY_WINDOW', 300),
        'block_duration' => (int) env('GUPA_BLOCK_DURATION', 3600),
        'log_enabled' => (bool) env('GUPA_LOG_ENABLED', true),
        'storage' => env('GUPA_STORAGE', 'cache'),
        'suspicious_threshold' => (int) env('GUPA_SUSPICIOUS_THRESHOLD', 10),
        'log_retention_days' => (int) env('GUPA_LOG_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist
    |--------------------------------------------------------------------------
    |
    | IP yang selalu diizinkan melewati semua deteksi.
    | Mendukung exact match, CIDR notation (10.0.0.0/8), dan wildcard (192.168.*.*).
    |
    */

    'whitelist' => [
        'enabled' => (bool) env('GUPA_WHITELIST_ENABLED', true),
        'ips' => ['127.0.0.1', '::1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklist
    |--------------------------------------------------------------------------
    |
    | IP yang langsung diblokir tanpa scoring.
    |
    */

    'blacklist' => [
        'enabled' => (bool) env('GUPA_BLACKLIST_ENABLED', false),
        'ips' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Detectors
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk masing-masing detector scoring.
    | Setiap detector bisa diaktifkan/nonaktifkan secara independen.
    |
    */

    'detectors' => [

        /*
        | Velocity: Deteksi terlalu banyak request dalam waktu singkat.
        */
        'velocity' => [
            'enabled' => (bool) env('GUPA_VELOCITY_ENABLED', true),
            'max_requests' => (int) env('GUPA_VELOCITY_MAX_REQUESTS', 60),
            'window' => (int) env('GUPA_VELOCITY_WINDOW', 60),
            'score' => (int) env('GUPA_VELOCITY_SCORE', 15),
        ],

        /*
        | Honeypot: Deteksi bot yang mengisi field tersembunyi atau akses route tersembunyi.
        |
        | routes   — exact match: salah satu segment path harus sama persis
        | prefixes — awalan: salah satu segment path harus diawali string ini
        |
        | Keduanya dicek per-segment (pisah '/'), bukan terhadap path utuh.
        |
        | Contoh routes (exact match per segment):
        |   'routes' => ['wp-login.php', 'xmlrpc.php']
        |   → /wp-login.php        ✓ terdeteksi (segment 'wp-login.php' match)
        |   → /2023/wp-login.php   ✓ terdeteksi (segment 'wp-login.php' match)
        |   → /sub/wp-login/x      ✓ terdeteksi (segment 'wp-login.php' match)
        |   → /wp-login            ✗ tidak (segment 'wp-login' ≠ 'wp-login.php')
        |   → /login               ✗ tidak
        |
        | Contoh prefixes (awalan per segment):
        |   'prefixes' => ['wp-', 'wpv-']
        |   → /wp-admin.php        ✓ terdeteksi (segment 'wp-admin.php' diawali 'wp-')
        |   → /2023/wp-login.php   ✓ terdeteksi (segment 'wp-login.php' diawali 'wp-')
        |   → /wpv-view/123        ✓ terdeteksi (segment 'wpv-view' diawali 'wpv-')
        |   → /admin               ✗ tidak
        |   → /wp-admin-old        ✓ terdeteksi (segment 'wp-admin-old' diawali 'wp-')
        |
        | Gabungan routes + prefixes keduanya dicek, skor sama.
        */
        'honeypot' => [
            'enabled' => (bool) env('GUPA_HONEYPOT_ENABLED', true),
            'field_name' => env('GUPA_HONEYPOT_FIELD', 'website_url'),
            'routes' => [],
            'prefixes' => ['wp-'],
            'score' => (int) env('GUPA_HONEYPOT_SCORE', 50),
        ],

        /*
        | Header: Deteksi user-agent bot, header Accept/Accept-Language missing, dll.
        */
        'header' => [
            'enabled' => (bool) env('GUPA_HEADER_ENABLED', true),
            'bot_user_agent_score' => (int) env('GUPA_HEADER_BOT_UA_SCORE', 20),
            'missing_accept_score' => (int) env('GUPA_HEADER_MISSING_ACCEPT_SCORE', 10),
            'missing_accept_language_score' => (int) env('GUPA_HEADER_MISSING_ACCEPT_LANG_SCORE', 10),
            'missing_referer_post_score' => (int) env('GUPA_HEADER_MISSING_REFERER_POST_SCORE', 5),
        ],

        /*
        | Not Found: Deteksi excessive 404 (scanning behavior).
        */
        'notfound' => [
            'enabled' => (bool) env('GUPA_NOTFOUND_ENABLED', true),
            'max_404s' => (int) env('GUPA_NOTFOUND_MAX_404S', 10),
            'window' => (int) env('GUPA_NOTFOUND_WINDOW', 60),
            'score' => (int) env('GUPA_NOTFOUND_SCORE', 20),
        ],

        /*
        | Rate Limit: Deteksi IP yang melebihi rate limit.
        */
        'rate_limit' => [
            'enabled' => (bool) env('GUPA_RATE_LIMIT_DETECTOR_ENABLED', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-IP rate limiting using Laravel's built-in RateLimiter.
    | Melebihi max_attempts akan mengembalikan 429 dan menambah skor.
    |
    | max_attempts  — Maksimal request per window (default: 60)
    | decay_seconds — Durasi window dalam detik (default: 60)
    | score        — Skor yang ditambahkan saat rate limit terlampaui (default: 10)
    |
    */

    'rate_limits' => [
        'enabled' => (bool) env('GUPA_RATE_LIMITS_ENABLED', false),

        'default' => [
            'max_attempts' => (int) env('GUPA_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('GUPA_RATE_LIMIT_DECAY_SECONDS', 60),
            'score' => (int) env('GUPA_RATE_LIMIT_SCORE', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Kirim notifikasi saat IP diblokir/unblock.
    | Mendukung channel: webhook (HTTP POST) dan email.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('GUPA_NOTIFICATIONS_ENABLED', false),

        'channels' => [
            'webhook' => [
                'enabled' => (bool) env('GUPA_WEBHOOK_ENABLED', false),
                'url' => env('GUPA_WEBHOOK_URL', ''),
                'secret' => env('GUPA_WEBHOOK_SECRET', ''),
            ],

            'email' => [
                'enabled' => (bool) env('GUPA_EMAIL_ENABLED', false),
                'to' => env('GUPA_EMAIL_TO', ''),
                'from' => env('GUPA_EMAIL_FROM', ''),
            ],
        ],
    ],

];
