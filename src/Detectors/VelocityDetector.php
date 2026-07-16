<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VelocityDetector implements DetectorInterface
{
    private const REQUEST_LOG_KEY = 'gupa:velocity:requests:';
    private const DEFAULT_MAX_REQUESTS = 60;
    private const DEFAULT_WINDOW = 60;
    private const DEFAULT_SCORE = 15;

    public function detect(Request $request): int
    {
        $ip = $request->ip();
        $maxRequests = config('gupa.detectors.velocity.max_requests', self::DEFAULT_MAX_REQUESTS);
        $window = config('gupa.detectors.velocity.window', self::DEFAULT_WINDOW);
        $score = config('gupa.detectors.velocity.score', self::DEFAULT_SCORE);

        $key = self::REQUEST_LOG_KEY . $ip;
        $requests = Cache::get($key, []);
        $now = time();

        $requests = array_filter($requests, fn ($ts) => $ts > $now - $window);

        $requests[] = $now;
        Cache::put($key, $requests, $window + 60);

        $recentCount = count($requests);

        if ($recentCount > $maxRequests) {
            return $score;
        }

        return 0;
    }

    public function isEnabled(): bool
    {
        return (bool) config('gupa.detectors.velocity.enabled', true);
    }

    public function getName(): string
    {
        return 'velocity';
    }
}
