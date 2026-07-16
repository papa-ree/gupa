# Gupa — IP Protection Package Roadmap

> `bale/gupa` — IP blocking, rate limiting, bot detection untuk Bale Front monorepo.
> Modeled after [laravel-bot-guardian](https://github.com/febryntara/laravel-bot-guardian).

## Phase Tracker

| Phase | Name | Status | Tests |
|-------|------|--------|-------|
| 1 | Core Foundation | **DONE** | 32 |
| 2 | Default Detectors | **DONE** | +24 |
| 3 | Notifications | **DONE** | +12 |
| 4 | CIDR & Wildcard Whitelist | **DONE** | +9 |
| 5 | Dashboard & Routes | **DONE** | +10 |
| 6 | Rate Limiter | **DONE** | +6 |

---

## Phase 1 — Core Foundation ✅

> Package scaffolding, middleware flow, cache-based scoring & blocking.

### Deliverables
- [x] `composer.json` — PSR-4 `Bale\Gupa\`, Pest, Orchestra Testbench, auto-discovery
- [x] `config/gupa.php` — master (enabled, threshold, decay, block_duration), whitelist, blacklist
- [x] `src/Detectors/DetectorInterface.php` — contract: `detect()`, `isEnabled()`, `getName()`
- [x] `src/Support/WhitelistChecker.php` — exact IP match, config + dynamic cache
- [x] `src/Actions/BlockAction.php` — cache-based block/unblock/pending/recidivist
- [x] `src/Actions/LogAction.php` — wraps `Log::warning/info/critical`
- [x] `src/Scorer/ScoreCalculator.php` — orchestrates detectors, atomic scoring, TTL decay
- [x] `src/Middleware/GuardianMiddleware.php` — `handle()` + `terminate()` flow
- [x] `src/GupaServiceProvider.php` — singleton bindings, config merge, middleware alias
- [x] `src/Commands/UnblockCommand.php` — `gupa:unblock {ip}`
- [x] `src/Commands/StatsCommand.php` — `gupa:stats {--json}`
- [x] `tests/` — 32 tests (WhitelistChecker, BlockAction, ScoreCalculator, Feature)
- [x] Main app integration — `composer.json` path repo + `bootstrap/app.php` global middleware

---

## Phase 2 — Default Detectors ✅

> 4 built-in detectors yang aktif by default, masing-masing configurable & toggleable.

### Deliverables
- [x] `src/Detectors/VelocityDetector.php` — too many requests in time window (score: 15)
- [x] `src/Detectors/HoneypotDetector.php` — hidden field filled / honeypot route (score: 50)
- [x] `src/Detectors/HeaderDetector.php` — bot UA, missing Accept/Accept-Language, POST w/o Referer (score: 20+10+10+5)
- [x] `src/Detectors/NotFoundDetector.php` — excessive 404s per IP (score: 20, recorded in `terminate()`)
- [x] `config/gupa.php` — `detectors.velocity`, `detectors.honeypot`, `detectors.header`, `detectors.notfound`
- [x] `GupaServiceProvider` — auto-registers all 4 detectors into ScoreCalculator
- [x] `GuardianMiddleware` — hooks NotFoundDetector recording on 404 response
- [x] 24 new tests (VelocityDetectorTest, HoneypotDetectorTest, HeaderDetectorTest, NotFoundDetectorTest)

---

## Phase 3 — Notifications ✅

> Kirim notifikasi (webhook & email) saat IP diblokir/unblock.

### Deliverables
- [x] `src/Actions/NotifyAction.php` — orchestrator, dispatches to enabled channels
- [x] `src/Notifications/BlockNotification.php` — value object: `toArray()`, `toEmailSubject()`, `toEmailBody()`
- [x] `src/Notifications/Channels/WebhookChannel.php` — HTTP POST via `Http::post()`, optional `X-Gupa-Secret`
- [x] `src/Notifications/Channels/EmailChannel.php` — plain-text email via `Mail::raw()`
- [x] `config/gupa.php` — `notifications.enabled`, `notifications.channels.webhook`, `notifications.channels.email`
- [x] `GupaServiceProvider` — registers `NotifyAction` singleton
- [x] `GuardianMiddleware` — calls `NotifyAction` on block, permanent block, pending block
- [x] 12 new tests (NotifyActionTest: config, webhook, email, dispatch)

---

## Phase 4 — CIDR & Wildcard Whitelist ✅

> Extend WhitelistChecker untuk mendukung CIDR notation dan wildcard pattern.

### Deliverables
- [x] `src/Support/WhitelistChecker.php` — add `matchesCidr($ip, $cidr)` method
- [x] `src/Support/WhitelistChecker.php` — add `matchesWildcard($ip, $pattern)` method
- [x] `src/Support/WhitelistChecker.php` — extend `isWhitelisted()` to check CIDR & wildcard
- [x] `config/gupa.php` — whitelist `ips` supports `['10.0.0.0/8', '192.168.*.*']` format
- [x] `config/gupa.php` — blacklist `ips` supports same CIDR & wildcard format
- [x] Tests for CIDR matching (exact subnet, within range, outside range)
- [x] Tests for wildcard matching (partial, full octet, multiple)
- [x] Tests for combined exact + CIDR + wildcard in single config

---

## Phase 5 — Dashboard & Routes ✅

> Artisan commands untuk monitoring & management.

### Deliverables
- [x] `src/Commands/DashboardCommand.php` — `gupa:dashboard` (terminal overview + JSON)
- [x] `src/Commands/ClearScoreCommand.php` — `gupa:clear-score {ip}` (manual score reset)
- [x] `src/Commands/WhitelistCommand.php` — `gupa:whitelist {--add=} {--remove=} {--list}`
- [x] `src/Commands/BlacklistCommand.php` — `gupa:blacklist {--add=} {--remove=} {--list}`
- [x] Commands registered in `GupaServiceProvider`
- [x] 10 new tests (`tests/Unit/CommandTest.php`)

---

## Phase 6 — Rate Limiter ✅

> Per-IP rate limiting using Laravel's built-in RateLimiter, integrated with Gupa scoring.

### Deliverables
- [x] `src/Detectors/RateLimitDetector.php` — checks rate limiter state, adds score on violation
- [x] `config/gupa.php` — `rate_limits` section (enabled, max_attempts, decay_seconds, score)
- [x] `GuardianMiddleware` — rate limit check in `handle()`, returns 429 JSON with `Retry-After`
- [x] `RateLimitDetector` auto-registered in `GupaServiceProvider`
- [x] 6 new tests (`tests/Unit/RateLimitDetectorTest.php`)

---

## Architecture

```
packages/bale-gupa/
├── config/gupa.php
├── src/
│   ├── Actions/
│   │   ├── BlockAction.php
│   │   ├── LogAction.php
│   │   └── NotifyAction.php
│   ├── Commands/
│   │   ├── BlacklistCommand.php
│   │   ├── ClearScoreCommand.php
│   │   ├── DashboardCommand.php
│   │   ├── StatsCommand.php
│   │   ├── UnblockCommand.php
│   │   └── WhitelistCommand.php
│   ├── Detectors/
│   │   ├── DetectorInterface.php
│   │   ├── HeaderDetector.php
│   │   ├── HoneypotDetector.php
│   │   ├── NotFoundDetector.php
│   │   ├── RateLimitDetector.php
│   │   └── VelocityDetector.php
│   ├── Middleware/
│   │   └── GuardianMiddleware.php
│   ├── Notifications/
│   │   ├── BlockNotification.php
│   │   └── Channels/
│   │       ├── EmailChannel.php
│   │       └── WebhookChannel.php
│   ├── Scorer/
│   │   └── ScoreCalculator.php
│   ├── Support/
│   │   └── WhitelistChecker.php
│   └── GupaServiceProvider.php
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
