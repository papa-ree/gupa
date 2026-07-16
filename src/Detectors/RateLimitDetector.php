<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitDetector implements DetectorInterface
{
    private const RATE_LIMIT_KEY_PREFIX = 'gupa:ratelimit:';
    private const DEFAULT_SCORE = 10;

    public function detect(Request $request): int
    {
        if (!$this->isRateLimitingEnabled()) {
            return 0;
        }

        $key = self::RATE_LIMIT_KEY_PREFIX . $request->ip();
        $maxAttempts = config('gupa.rate_limits.default.max_attempts', 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return config('gupa.rate_limits.default.score', self::DEFAULT_SCORE);
        }

        return 0;
    }

    public function isEnabled(): bool
    {
        return (bool) config('gupa.detectors.rate_limit.enabled', true);
    }

    public function getName(): string
    {
        return 'rate_limit';
    }

    private function isRateLimitingEnabled(): bool
    {
        return (bool) config('gupa.rate_limits.enabled', false);
    }
}
