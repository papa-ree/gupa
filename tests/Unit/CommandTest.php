<?php

use Bale\Gupa\Actions\BlockAction;
use Illuminate\Support\Facades\Cache;

it('runs dashboard command', function () {
    $this->artisan('gupa:dashboard')
        ->expectsTable(['Setting', 'Value'], [
            ['Enabled', 'Yes'],
            ['Threshold', 100],
            ['Block Duration', '3600s'],
        ])
        ->assertExitCode(0);
});

it('runs dashboard command with json option', function () {
    $this->artisan('gupa:dashboard', ['--json' => true])
        ->assertExitCode(0);
});

it('clears score for valid IP', function () {
    $calculator = app(\Bale\Gupa\Scorer\ScoreCalculator::class);
    $request = \Illuminate\Http\Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.50',
    ]);

    $calculator->increment($request, 50);

    $this->artisan('gupa:clear-score', ['ip' => '10.0.0.50'])
        ->expectsOutput('Score cleared for IP 10.0.0.50 (was: 50)')
        ->assertExitCode(0);
});

it('rejects invalid IP in clear-score', function () {
    $this->artisan('gupa:clear-score', ['ip' => 'not-an-ip'])
        ->expectsOutput('Invalid IP address: not-an-ip')
        ->assertExitCode(1);
});

it('adds IP to dynamic whitelist', function () {
    $this->artisan('gupa:whitelist', ['--add' => '10.99.99.1'])
        ->expectsOutput('Added 10.99.99.1 to dynamic whitelist.')
        ->assertExitCode(0);

    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    expect($checker->isWhitelisted('10.99.99.1'))->toBeTrue();
});

it('removes IP from dynamic whitelist', function () {
    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    $checker->whitelist('10.99.99.2');

    $this->artisan('gupa:whitelist', ['--remove' => '10.99.99.2'])
        ->expectsOutput('Removed 10.99.99.2 from dynamic whitelist.')
        ->assertExitCode(0);

    expect($checker->isWhitelisted('10.99.99.2'))->toBeFalse();
});

it('lists dynamic whitelist', function () {
    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    $checker->whitelist('10.99.99.3');

    $this->artisan('gupa:whitelist', ['--list' => true])
        ->expectsOutput('Whitelisted IPs:')
        ->assertExitCode(0);
});

it('adds IP to dynamic blacklist', function () {
    config()->set('gupa.blacklist.enabled', true);

    $this->artisan('gupa:blacklist', ['--add' => '10.99.99.10'])
        ->expectsOutput('Added 10.99.99.10 to dynamic blacklist.')
        ->assertExitCode(0);

    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    expect($checker->isBlacklisted('10.99.99.10'))->toBeTrue();
});

it('removes IP from dynamic blacklist', function () {
    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    $checker->blacklist('10.99.99.11');

    $this->artisan('gupa:blacklist', ['--remove' => '10.99.99.11'])
        ->expectsOutput('Removed 10.99.99.11 from dynamic blacklist.')
        ->assertExitCode(0);

    expect($checker->isBlacklisted('10.99.99.11'))->toBeFalse();
});

it('lists dynamic blacklist', function () {
    $checker = app(\Bale\Gupa\Support\WhitelistChecker::class);
    $checker->blacklist('10.99.99.12');

    $this->artisan('gupa:blacklist', ['--list' => true])
        ->expectsOutput('Blacklisted IPs:')
        ->assertExitCode(0);
});
