<?php

use Bale\Gupa\Detectors\HeaderDetector;
use Illuminate\Http\Request;

it('detects bot user agent', function () {
    config()->set('gupa.detectors.header.enabled', true);
    config()->set('gupa.detectors.header.bot_user_agent_score', 20);
    config()->set('gupa.detectors.header.missing_accept_score', 10);
    config()->set('gupa.detectors.header.missing_accept_language_score', 10);
    config()->set('gupa.detectors.header.missing_referer_post_score', 5);

    $detector = new HeaderDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'python-requests/2.28.0',
        'HTTP_ACCEPT' => 'text/html',
        'HTTP_ACCEPT_LANGUAGE' => 'en-US',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBeGreaterThanOrEqual(20);
});

it('detects missing Accept header', function () {
    config()->set('gupa.detectors.header.enabled', true);
    config()->set('gupa.detectors.header.bot_user_agent_score', 20);
    config()->set('gupa.detectors.header.missing_accept_score', 10);
    config()->set('gupa.detectors.header.missing_accept_language_score', 10);
    config()->set('gupa.detectors.header.missing_referer_post_score', 5);

    $detector = new HeaderDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ]);
    $request->headers->remove('Accept');
    $request->headers->remove('Accept-Language');

    $result = $detector->detect($request);

    expect($result)->toBeGreaterThanOrEqual(10);
});

it('detects missing Accept-Language header', function () {
    config()->set('gupa.detectors.header.enabled', true);
    config()->set('gupa.detectors.header.bot_user_agent_score', 20);
    config()->set('gupa.detectors.header.missing_accept_score', 10);
    config()->set('gupa.detectors.header.missing_accept_language_score', 10);
    config()->set('gupa.detectors.header.missing_referer_post_score', 5);

    $detector = new HeaderDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
        'HTTP_ACCEPT' => 'text/html',
    ]);
    $request->headers->remove('Accept-Language');

    $result = $detector->detect($request);

    expect($result)->toBeGreaterThanOrEqual(10);
});

it('detects missing Referer on POST', function () {
    config()->set('gupa.detectors.header.enabled', true);
    config()->set('gupa.detectors.header.bot_user_agent_score', 20);
    config()->set('gupa.detectors.header.missing_accept_score', 10);
    config()->set('gupa.detectors.header.missing_accept_language_score', 10);
    config()->set('gupa.detectors.header.missing_referer_post_score', 5);

    $detector = new HeaderDetector();
    $request = Request::create('/test', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
        'HTTP_ACCEPT' => 'text/html',
        'HTTP_ACCEPT_LANGUAGE' => 'en-US',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBeGreaterThanOrEqual(5);
});

it('returns zero for clean browser request', function () {
    config()->set('gupa.detectors.header.enabled', true);
    config()->set('gupa.detectors.header.bot_user_agent_score', 20);
    config()->set('gupa.detectors.header.missing_accept_score', 10);
    config()->set('gupa.detectors.header.missing_accept_language_score', 10);
    config()->set('gupa.detectors.header.missing_referer_post_score', 5);

    $detector = new HeaderDetector();
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
    ]);

    $result = $detector->detect($request);

    expect($result)->toBe(0);
});

it('returns correct name', function () {
    $detector = new HeaderDetector();

    expect($detector->getName())->toBe('header');
});

it('respects enabled config', function () {
    config()->set('gupa.detectors.header.enabled', false);

    $detector = new HeaderDetector();

    expect($detector->isEnabled())->toBeFalse();
});
