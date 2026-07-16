<?php

use Bale\Gupa\Middleware\GuardianMiddleware;
use Illuminate\Support\Facades\Cache;

it('passes through when gupa is disabled', function () {
    config()->set('gupa.master.enabled', false);

    $response = $this->get('/test-guardian');

    $response->assertOk();
});

it('passes through for whitelisted IP', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['127.0.0.1']);

    $response = $this->get('/test-guardian');

    $response->assertOk();
});

it('blocks blacklisted IP', function () {
    config()->set('gupa.whitelist.ips', []);
    config()->set('gupa.blacklist.enabled', true);
    config()->set('gupa.blacklist.ips', ['127.0.0.1']);

    $response = $this->get('/test-guardian');

    $response->assertStatus(403);
    $response->assertJson([
        'error' => 'Access denied.',
    ]);
});

it('blocks already blocked IP', function () {
    config()->set('gupa.whitelist.ips', []);
    Cache::put('gupa:blocked:127.0.0.1', true, 3600);

    $response = $this->get('/test-guardian');

    $response->assertStatus(403);
});

it('applies pending block and returns 403', function () {
    config()->set('gupa.whitelist.ips', []);
    Cache::put('gupa:pending_block:127.0.0.1', true, 3600);

    $response = $this->get('/test-guardian');

    $response->assertStatus(403);
    expect(Cache::has('gupa:pending_block:127.0.0.1'))->toBeFalse();
    expect(Cache::has('gupa:blocked:127.0.0.1'))->toBeTrue();
});

it('returns JSON response on blocked request', function () {
    config()->set('gupa.whitelist.ips', []);
    Cache::put('gupa:blocked:127.0.0.1', true, 3600);

    $response = $this->get('/test-guardian');

    $response->assertStatus(403);
    $response->assertJsonStructure([
        'error',
        'message',
    ]);
});
