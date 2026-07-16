<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotFoundDetector implements DetectorInterface
{
    private const NOTFOUND_KEY_PREFIX = 'gupa:notfound:';
    private const DEFAULT_MAX_404S = 10;
    private const DEFAULT_WINDOW = 60;
    private const DEFAULT_SCORE = 20;

    public function detect(Request $request): int
    {
        $ip = $request->ip();
        $max404s = config('gupa.detectors.notfound.max_404s', self::DEFAULT_MAX_404S);
        $window = config('gupa.detectors.notfound.window', self::DEFAULT_WINDOW);
        $score = config('gupa.detectors.notfound.score', self::DEFAULT_SCORE);

        $key = self::NOTFOUND_KEY_PREFIX . $ip;
        $log = Cache::get($key, []);
        $now = time();

        $log = array_filter($log, fn ($ts) => $ts > $now - $window);

        Cache::put($key, $log, $window + 60);

        if (count($log) > $max404s) {
            return $score;
        }

        return 0;
    }

    public function recordNotFound(Request $request): void
    {
        $ip = $request->ip();
        $window = config('gupa.detectors.notfound.window', self::DEFAULT_WINDOW);

        $key = self::NOTFOUND_KEY_PREFIX . $ip;
        $log = Cache::get($key, []);
        $log[] = time();

        Cache::put($key, $log, $window + 60);
    }

    public function isEnabled(): bool
    {
        return (bool) config('gupa.detectors.notfound.enabled', true);
    }

    public function getName(): string
    {
        return 'not_found';
    }
}
