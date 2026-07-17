# Gupa — Roadmap

## Fitur yang Sudah Ada

- **Mesin Skor Perilaku** — Multi-detektor yang mengevaluasi setiap request secara real-time
- **5 Detektor** — Velocity, honeypot, header HTTP, serangan 404, rate limiting
- **Pemblokiran Otomatis** — Blocking IP sementara atau permanen dengan threshold configurable
- **Dual Storage** — Mode cache-only (Redis/file) atau pure database (zero cache touch)
- **CIDR & Wildcard** — Whitelist/blacklist seluruh subnet atau range IP
- **Blacklist/Whitelist Dinamis** — Tambah atau hapus IP saat runtime melalui Artisan
- **Mutual Exclusion** — Menambah ke blacklist otomatis menghapus dari whitelist, dan sebaliknya
- **Logging Path Mencurigakan** — Mencatat detail request saat skor mencapai threshold suspicious
- **Log Retention & Pruning** — Auto-prune log berdasarkan `GUPA_LOG_RETENTION_DAYS`
- **Pending Block** — Deteksi bot via 404 → defer block ke request berikutnya
- **Notifikasi** — Alert webhook dan email saat IP diblokir/diunblock
- **Dashboard & CLI** — `gupa:dashboard`, `gupa:stats`, `gupa:log`, dan manajemen IP lainnya
- **Honeypot** — Field tersembunyi, exact route match, dan prefix path matching
- **Recidivist Detection** — IP yang ≥3x diblokir dalam 24 jam otomatis permanent block
