<?php

use Bale\Gupa\Detectors\HoneypotDetector;
use Illuminate\Http\Request;

it('detects honeypot field filled', function () {
    config()->set('gupa.detectors.honeypot.enabled', true);
    config()->set('gupa.detectors.honeypot.field_name', 'website_url');
    config()->set('gupa.detectors.honeypot.routes', []);
    config()->set('gupa.detectors.honeypot.score', 50);

    $detector = new HoneypotDetector();
    $request = Request::create('/test', 'POST', [
        'website_url' => 'http://spam.com',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBe(50);
});

it('returns zero when honeypot field is empty', function () {
    config()->set('gupa.detectors.honeypot.enabled', true);
    config()->set('gupa.detectors.honeypot.field_name', 'website_url');
    config()->set('gupa.detectors.honeypot.routes', []);
    config()->set('gupa.detectors.honeypot.score', 50);

    $detector = new HoneypotDetector();
    $request = Request::create('/test', 'POST', [
        'name' => 'John',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBe(0);
});

it('detects honeypot route access', function () {
    config()->set('gupa.detectors.honeypot.enabled', true);
    config()->set('gupa.detectors.honeypot.field_name', 'website_url');
    config()->set('gupa.detectors.honeypot.routes', ['secret-admin', 'wp-admin']);
    config()->set('gupa.detectors.honeypot.score', 50);

    $detector = new HoneypotDetector();
    $request = Request::create('/secret-admin', 'GET');

    $result = $detector->detect($request);

    expect($result)->toBe(50);
});

it('returns zero for non-honeypot route', function () {
    config()->set('gupa.detectors.honeypot.enabled', true);
    config()->set('gupa.detectors.honeypot.field_name', 'website_url');
    config()->set('gupa.detectors.honeypot.routes', ['secret-admin']);
    config()->set('gupa.detectors.honeypot.score', 50);

    $detector = new HoneypotDetector();
    $request = Request::create('/about', 'GET');

    $result = $detector->detect($request);

    expect($result)->toBe(0);
});

it('returns correct name', function () {
    $detector = new HoneypotDetector();

    expect($detector->getName())->toBe('honeypot');
});

it('respects enabled config', function () {
    config()->set('gupa.detectors.honeypot.enabled', false);

    $detector = new HoneypotDetector();

    expect($detector->isEnabled())->toBeFalse();
});
