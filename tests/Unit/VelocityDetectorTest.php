<?php

use Bale\Gupa\Detectors\VelocityDetector;
use Illuminate\Http\Request;

it('detects high velocity requests', function () {
    config()->set('gupa.detectors.velocity.enabled', true);
    config()->set('gupa.detectors.velocity.max_requests', 5);
    config()->set('gupa.detectors.velocity.window', 60);
    config()->set('gupa.detectors.velocity.score', 15);

    $detector = new VelocityDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.1',
    ]);

    for ($i = 0; $i < 6; $i++) {
        $result = $detector->detect($request);
    }

    expect($result)->toBe(15);
});

it('returns zero when under velocity threshold', function () {
    config()->set('gupa.detectors.velocity.enabled', true);
    config()->set('gupa.detectors.velocity.max_requests', 100);
    config()->set('gupa.detectors.velocity.window', 60);
    config()->set('gupa.detectors.velocity.score', 15);

    $detector = new VelocityDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.2',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBe(0);
});

it('returns correct name', function () {
    $detector = new VelocityDetector();

    expect($detector->getName())->toBe('velocity');
});

it('respects enabled config', function () {
    config()->set('gupa.detectors.velocity.enabled', false);

    $detector = new VelocityDetector();

    expect($detector->isEnabled())->toBeFalse();
});

it('tracks velocity per IP independently', function () {
    config()->set('gupa.detectors.velocity.enabled', true);
    config()->set('gupa.detectors.velocity.max_requests', 2);
    config()->set('gupa.detectors.velocity.window', 60);
    config()->set('gupa.detectors.velocity.score', 15);

    $detector = new VelocityDetector();

    $requestA = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.10',
    ]);
    $requestB = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.11',
    ]);

    $detector->detect($requestA);
    $detector->detect($requestA);
    $resultA = $detector->detect($requestA);

    $resultB = $detector->detect($requestB);

    expect($resultA)->toBe(15);
    expect($resultB)->toBe(0);
});
