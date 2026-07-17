<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HoneypotDetector implements DetectorInterface
{
    private const HONEYPOT_KEY_PREFIX = 'gupa:honeypot:';
    private const DEFAULT_SCORE = 50;

    public function detect(Request $request): int
    {
        $score = config('gupa.detectors.honeypot.score', self::DEFAULT_SCORE);

        $honeypotField = config('gupa.detectors.honeypot.field_name', 'website_url');
        $honeypotRoutes = config('gupa.detectors.honeypot.routes', []);
        $honeypotPrefixes = config('gupa.detectors.honeypot.prefixes', []);

        if ($this->isHoneypotFieldFilled($request, $honeypotField)) {
            return $score;
        }

        if ($this->isHoneypotRoute($request, $honeypotRoutes)) {
            return $score;
        }

        if ($this->matchesHoneypotPrefix($request, $honeypotPrefixes)) {
            return $score;
        }

        return 0;
    }

    private function isHoneypotFieldFilled(Request $request, string $fieldName): bool
    {
        $value = $request->input($fieldName);

        return !empty($value);
    }

    private function isHoneypotRoute(Request $request, array $honeypotRoutes): bool
    {
        if (empty($honeypotRoutes)) {
            return false;
        }

        $segments = explode('/', $request->path());

        foreach ($segments as $segment) {
            if (in_array($segment, $honeypotRoutes, true)) {
                return true;
            }
        }

        return false;
    }

    private function matchesHoneypotPrefix(Request $request, array $prefixes): bool
    {
        if (empty($prefixes)) {
            return false;
        }

        $segments = explode('/', $request->path());

        foreach ($segments as $segment) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($segment, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isEnabled(): bool
    {
        return (bool) config('gupa.detectors.honeypot.enabled', true);
    }

    public function getName(): string
    {
        return 'honeypot';
    }
}
