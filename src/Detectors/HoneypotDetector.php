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

        if ($this->isHoneypotFieldFilled($request, $honeypotField)) {
            return $score;
        }

        if ($this->isHoneypotRoute($request, $honeypotRoutes)) {
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

        $path = $request->path();

        return in_array($path, $honeypotRoutes, true);
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
