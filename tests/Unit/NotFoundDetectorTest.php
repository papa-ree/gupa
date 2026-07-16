<?php

use Bale\Gupa\Detectors\NotFoundDetector;
use Illuminate\Http\Request;

it('detects excessive 404s', function () {
    config()->set('gupa.detectors.notfound.enabled', true);
    config()->set('gupa.detectors.notfound.max_404s', 3);
    config()->set('gupa.detectors.notfound.window', 60);
    config()->set('gupa.detectors.notfound.score', 20);

    $detector = new NotFoundDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.1',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $detector->recordNotFound($request);
    }

    $result = $detector->detect($request);

    expect($result)->toBe(20);
});

it('returns zero when under 404 threshold', function () {
    config()->set('gupa.detectors.notfound.enabled', true);
    config()->set('gupa.detectors.notfound.max_404s', 10);
    config()->set('gupa.detectors.notfound.window', 60);
    config()->set('gupa.detectors.notfound.score', 20);

    $detector = new NotFoundDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.2',
    ]);

    $detector->recordNotFound($request);

    $result = $detector->detect($request);

    expect($result)->toBe(0);
});

it('returns correct name', function () {
    $detector = new NotFoundDetector();

    expect($detector->getName())->toBe('not_found');
});

it('respects enabled config', function () {
    config()->set('gupa.detectors.notfound.enabled', false);

    $detector = new NotFoundDetector();

    expect($detector->isEnabled())->toBeFalse();
});

it('tracks 404s per IP independently', function () {
    config()->set('gupa.detectors.notfound.enabled', true);
    config()->set('gupa.detectors.notfound.max_404s', 2);
    config()->set('gupa.detectors.notfound.window', 60);
    config()->set('gupa.detectors.notfound.score', 20);

    $detector = new NotFoundDetector();

    $requestA = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.10',
    ]);
    $requestB = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.11',
    ]);

    $detector->recordNotFound($requestA);
    $detector->recordNotFound($requestA);
    $detector->recordNotFound($requestA);

    $resultA = $detector->detect($requestA);
    $resultB = $detector->detect($requestB);

    expect($resultA)->toBe(20);
    expect($resultB)->toBe(0);
});
