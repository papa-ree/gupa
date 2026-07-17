<?php

namespace Bale\Gupa\Middleware;

use Bale\Gupa\Actions\BlockAction;
use Bale\Gupa\Actions\LogAction;
use Bale\Gupa\Actions\NotifyAction;
use Bale\Gupa\Detectors\NotFoundDetector;
use Bale\Gupa\Models\BlockedIp as BlockedIpModel;
use Bale\Gupa\Scorer\ScoreCalculator;
use Bale\Gupa\Support\WhitelistChecker;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class GuardianMiddleware
{
    private const RATE_LIMIT_KEY_PREFIX = 'gupa:ratelimit:';

    public function __construct(
        private WhitelistChecker $whitelistChecker,
        private BlockAction $blockAction,
        private ScoreCalculator $scoreCalculator,
        private LogAction $logAction,
        private NotFoundDetector $notFoundDetector,
        private NotifyAction $notifyAction,
    ) {}

    public function handle($request, Closure $next): Response
    {
        if (!$this->isEnabled()) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($this->whitelistChecker->isWhitelisted($ip)) {
            return $next($request);
        }

        if ($this->whitelistChecker->isBlacklisted($ip)) {
            return $this->blockedResponse($ip, 'blacklisted');
        }

        if ($this->blockAction->isBlocked($ip)) {
            return $this->blockedResponse($ip, 'already blocked');
        }

        if ($this->useDatabase() && !$this->blockAction->isBlocked($ip)) {
            if ($this->syncBlockedFromDatabase($ip)) {
                return $this->blockedResponse($ip, 'already blocked (synced from database)');
            }
        }

        if ($this->blockAction->hasPendingBlock($ip)) {
            $this->blockAction->applyPendingBlock($ip);
            $this->logAction->block($ip, 'Applied pending block from previous 404', 0);
            $this->notifyAction->block($ip, 'Applied pending block from previous 404', 0);

            return $this->blockedResponse($ip, 'pending block applied');
        }

        if ($this->isRateLimitEnabled() && $this->isRateLimited($ip)) {
            return $this->rateLimitedResponse($ip);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    public function terminate($request, $response): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $ip = $request->ip();

        if ($this->whitelistChecker->isWhitelisted($ip)) {
            return;
        }

        if ($this->blockAction->isBlocked($ip)) {
            return;
        }

        if ($response->getStatusCode() === 404) {
            $this->notFoundDetector->recordNotFound($request);
        }

        $this->processScoring($request, $response);
    }

    private function processScoring($request, Response $response): void
    {
        $score = $this->scoreCalculator->calculate($request);

        if ($score <= 0) {
            return;
        }

        $ip = $request->ip();
        $newTotal = $this->scoreCalculator->increment($request, $score);

        $this->logAction->logSuspiciousRequest($request, $newTotal);

        if ($this->scoreCalculator->shouldBlock($request)) {
            $this->executeBlock($ip, $newTotal);
        }
    }

    private function executeBlock(string $ip, int $score): void
    {
        $blockCount = $this->blockAction->getBlockCount($ip);
        $maxBlocks = 3;
        $isRecidivist = $blockCount >= $maxBlocks;

        if ($isRecidivist) {
            $this->blockAction->execute($ip, permanent: true);
            $this->logAction->permanentBlock($ip, "Recidivist: {$blockCount} blocks in 24h", $blockCount);
            $this->notifyAction->block($ip, "Recidivist: {$blockCount} blocks in 24h", $score, permanent: true);
        } else {
            $this->blockAction->execute($ip);
            $this->logAction->block($ip, "Score threshold exceeded: {$score}", $score);
            $this->notifyAction->block($ip, "Score threshold exceeded: {$score}", $score);
        }
    }

    private function isRateLimited(string $ip): bool
    {
        $key = self::RATE_LIMIT_KEY_PREFIX . $ip;
        $maxAttempts = config('gupa.rate_limits.default.max_attempts', 60);

        RateLimiter::hit($key, config('gupa.rate_limits.default.decay_seconds', 60));

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    private function blockedResponse(string $ip, string $reason): Response
    {
        return response()->json([
            'error' => 'Access denied.',
            'message' => 'Your IP has been blocked due to suspicious activity.',
        ], 403);
    }

    private function rateLimitedResponse(string $ip): Response
    {
        $retryAfter = config('gupa.rate_limits.default.decay_seconds', 60);

        return response()->json([
            'error' => 'Rate limit exceeded.',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
        ]);
    }

    private function isRateLimitEnabled(): bool
    {
        return (bool) config('gupa.rate_limits.enabled', false);
    }

    private function isEnabled(): bool
    {
        return (bool) config('gupa.master.enabled', true);
    }

    private function useDatabase(): bool
    {
        return config('gupa.master.storage') === 'database';
    }

    private function syncBlockedFromDatabase(string $ip): bool
    {
        try {
            $blocked = BlockedIpModel::where('ip', $ip)->notExpired()->first();

            if (!$blocked) {
                return false;
            }

            if ($blocked->is_permanent) {
                Cache::forever('gupa:blocked:' . $ip, true);
                Cache::forever('gupa:permanent:' . $ip, true);
            } else {
                $remaining = $blocked->expires_at->diffInSeconds(now());
                if ($remaining > 0) {
                    Cache::put('gupa:blocked:' . $ip, true, $remaining);
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
