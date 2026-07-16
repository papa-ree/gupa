<?php

namespace Bale\Gupa\Scorer;

use Bale\Gupa\Detectors\DetectorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ScoreCalculator
{
    private const SCORE_KEY_PREFIX = 'gupa:score:';

    private Collection $detectors;

    public function __construct()
    {
        $this->detectors = collect();
    }

    public function register(DetectorInterface $detector): void
    {
        $this->detectors->push($detector);
    }

    public function calculate(Request $request): int
    {
        return $this->detectors
            ->filter(fn (DetectorInterface $d) => $d->isEnabled())
            ->sum(fn (DetectorInterface $d) => $d->detect($request));
    }

    public function increment(Request $request, int $score): int
    {
        if ($score <= 0) {
            return $this->getTotalScore($request);
        }

        $key = $this->scoreKey($request);
        $decay = config('gupa.master.score_decay_window', 300);

        $current = Cache::get($key, 0);
        $newTotal = $current + $score;

        Cache::put($key, $newTotal, $decay);

        return $newTotal;
    }

    public function shouldBlock(Request $request): bool
    {
        $total = $this->getTotalScore($request);
        $threshold = config('gupa.master.threshold', 100);

        return $total >= $threshold;
    }

    public function getTotalScore(Request $request): int
    {
        return (int) Cache::get($this->scoreKey($request), 0);
    }

    public function resetScore(Request $request): void
    {
        Cache::forget($this->scoreKey($request));
    }

    public function getActiveDetectors(): array
    {
        return $this->detectors
            ->filter(fn (DetectorInterface $d) => $d->isEnabled())
            ->map(fn (DetectorInterface $d) => $d->getName())
            ->values()
            ->toArray();
    }

    public function getAllDetectors(): array
    {
        return $this->detectors
            ->map(fn (DetectorInterface $d) => [
                'name' => $d->getName(),
                'enabled' => $d->isEnabled(),
            ])
            ->values()
            ->toArray();
    }

    private function scoreKey(Request $request): string
    {
        return self::SCORE_KEY_PREFIX . $request->ip();
    }
}
