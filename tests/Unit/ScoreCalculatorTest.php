<?php

use Bale\Gupa\Scorer\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

it('returns zero score with no detectors', function () {
    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $score = $calculator->calculate($request);

    expect($score)->toBe(0);
});

it('increments score atomically', function () {
    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $total1 = $calculator->increment($request, 20);
    expect($total1)->toBe(20);

    $total2 = $calculator->increment($request, 30);
    expect($total2)->toBe(50);
});

it('does not increment with zero or negative score', function () {
    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $calculator->increment($request, 20);
    $total = $calculator->increment($request, 0);

    expect($total)->toBe(20);
});

it('reads total score from cache', function () {
    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $calculator->increment($request, 40);

    expect($calculator->getTotalScore($request))->toBe(40);
});

it('resets score', function () {
    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $calculator->increment($request, 50);
    expect($calculator->getTotalScore($request))->toBe(50);

    $calculator->resetScore($request);
    expect($calculator->getTotalScore($request))->toBe(0);
});

it('should block when threshold reached', function () {
    config()->set('gupa.master.threshold', 100);

    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $calculator->increment($request, 100);

    expect($calculator->shouldBlock($request))->toBeTrue();
});

it('should not block below threshold', function () {
    config()->set('gupa.master.threshold', 100);

    $calculator = app(ScoreCalculator::class);
    $request = Request::create('/test', 'GET');

    $calculator->increment($request, 99);

    expect($calculator->shouldBlock($request))->toBeFalse();
});

it('returns registered detectors', function () {
    $calculator = app(ScoreCalculator::class);

    expect($calculator->getActiveDetectors())->not->toBeEmpty();
    expect($calculator->getAllDetectors())->not->toBeEmpty();
});

it('returns only enabled detectors', function () {
    config()->set('gupa.detectors.velocity.enabled', false);
    config()->set('gupa.detectors.honeypot.enabled', false);
    config()->set('gupa.detectors.header.enabled', false);
    config()->set('gupa.detectors.notfound.enabled', false);

    $calculator = new ScoreCalculator();

    expect($calculator->getActiveDetectors())->toBeEmpty();
    expect($calculator->getAllDetectors())->toBeEmpty();
});

it('tracks score per IP independently', function () {
    $calculator = app(ScoreCalculator::class);
    $request1 = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
    $request2 = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);

    $calculator->increment($request1, 50);
    $calculator->increment($request2, 30);

    expect($calculator->getTotalScore($request1))->toBe(50);
    expect($calculator->getTotalScore($request2))->toBe(30);
});
