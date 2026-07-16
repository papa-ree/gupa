<?php

use Bale\Gupa\Detectors\RateLimitDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('gupa.detectors.rate_limit.enabled', true);
    config()->set('gupa.rate_limits.enabled', true);
    config()->set('gupa.rate_limits.default.max_attempts', 3);
    config()->set('gupa.rate_limits.default.decay_seconds', 60);
    config()->set('gupa.rate_limits.default.score', 10);

    config()->set('gupa.whitelist.ips', []);
});

it('detects rate limit exceeded', function () {
    $detector = new RateLimitDetector();
    $request = Request::create('/api/test', 'GET');

    // Exhaust rate limit by hitting max_attempts + 1
    for ($i = 0; $i < 4; $i++) {
        \Illuminate\Support\Facades\RateLimiter::hit('gupa:ratelimit:127.0.0.1', 60);
    }

    $score = $detector->detect($request);

    expect($score)->toBe(10);
});

it('returns zero when under rate limit', function () {
    $detector = new RateLimitDetector();
    $request = Request::create('/api/test', 'GET');

    // Only 2 hits, under max_attempts of 3
    \Illuminate\Support\Facades\RateLimiter::hit('gupa:ratelimit:127.0.0.1', 60);
    \Illuminate\Support\Facades\RateLimiter::hit('gupa:ratelimit:127.0.0.1', 60);

    $score = $detector->detect($request);

    expect($score)->toBe(0);
});

it('returns correct name', function () {
    $detector = new RateLimitDetector();

    expect($detector->getName())->toBe('rate_limit');
});

it('respects enabled config', function () {
    config()->set('gupa.detectors.rate_limit.enabled', false);

    $detector = new RateLimitDetector();
    $request = Request::create('/api/test', 'GET');

    $score = $detector->detect($request);

    expect($score)->toBe(0);
});

it('returns configurable score', function () {
    config()->set('gupa.rate_limits.default.score', 25);

    $detector = new RateLimitDetector();
    $request = Request::create('/api/test', 'GET');

    for ($i = 0; $i < 4; $i++) {
        \Illuminate\Support\Facades\RateLimiter::hit('gupa:ratelimit:127.0.0.1', 60);
    }

    $score = $detector->detect($request);

    expect($score)->toBe(25);
});

it('returns zero when rate limiting is disabled', function () {
    config()->set('gupa.rate_limits.enabled', false);

    $detector = new RateLimitDetector();
    $request = Request::create('/api/test', 'GET');

    $score = $detector->detect($request);

    expect($score)->toBe(0);
});
