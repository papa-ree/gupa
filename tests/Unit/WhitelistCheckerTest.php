<?php

use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Support\Facades\Cache;

it('whitelists an IP from config', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['10.0.0.1']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('10.0.0.1'))->toBeTrue();
    expect($checker->isWhitelisted('192.168.1.1'))->toBeFalse();
});

it('whitelists an IP dynamically via cache', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', []);

    $checker = app(WhitelistChecker::class);

    $checker->whitelist('10.0.0.99');

    expect($checker->isWhitelisted('10.0.0.99'))->toBeTrue();
});

it('unwhitelists a dynamic IP', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', []);

    $checker = app(WhitelistChecker::class);

    $checker->whitelist('10.0.0.99');
    expect($checker->isWhitelisted('10.0.0.99'))->toBeTrue();

    $checker->unwhitelist('10.0.0.99');
    expect($checker->isWhitelisted('10.0.0.99'))->toBeFalse();
});

it('returns false when whitelist is disabled', function () {
    config()->set('gupa.whitelist.enabled', false);
    config()->set('gupa.whitelist.ips', ['10.0.0.1']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('10.0.0.1'))->toBeFalse();
});

it('blacklists an IP from config', function () {
    config()->set('gupa.blacklist.enabled', true);
    config()->set('gupa.blacklist.ips', ['192.168.1.100']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isBlacklisted('192.168.1.100'))->toBeTrue();
    expect($checker->isBlacklisted('10.0.0.1'))->toBeFalse();
});

it('blacklists an IP dynamically via cache', function () {
    config()->set('gupa.blacklist.enabled', true);
    config()->set('gupa.blacklist.ips', []);

    $checker = app(WhitelistChecker::class);

    $checker->blacklist('192.168.1.200');

    expect($checker->isBlacklisted('192.168.1.200'))->toBeTrue();
});

it('returns false when blacklist is disabled', function () {
    config()->set('gupa.blacklist.enabled', false);
    config()->set('gupa.blacklist.ips', ['192.168.1.100']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isBlacklisted('192.168.1.100'))->toBeFalse();
});

it('returns whitelisted IPs list', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['10.0.0.1', '10.0.0.2']);

    $checker = app(WhitelistChecker::class);
    $checker->whitelist('10.0.0.3');

    $ips = $checker->getWhitelistedIps();

    expect($ips)->toContain('10.0.0.1');
    expect($ips)->toContain('10.0.0.2');
    expect($ips)->toContain('10.0.0.3');
});

it('whitelists IP within CIDR range', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['10.0.0.0/24']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('10.0.0.1'))->toBeTrue();
    expect($checker->isWhitelisted('10.0.0.254'))->toBeTrue();
    expect($checker->isWhitelisted('10.0.1.1'))->toBeFalse();
});

it('whitelists IP within large CIDR range', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['10.0.0.0/8']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('10.0.0.1'))->toBeTrue();
    expect($checker->isWhitelisted('10.255.255.255'))->toBeTrue();
    expect($checker->isWhitelisted('11.0.0.1'))->toBeFalse();
});

it('blacklists IP within CIDR range', function () {
    config()->set('gupa.blacklist.enabled', true);
    config()->set('gupa.blacklist.ips', ['192.168.1.0/24']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isBlacklisted('192.168.1.50'))->toBeTrue();
    expect($checker->isBlacklisted('192.168.2.1'))->toBeFalse();
});

it('matches wildcard pattern', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['192.168.*.*']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('192.168.1.1'))->toBeTrue();
    expect($checker->isWhitelisted('192.168.100.200'))->toBeTrue();
    expect($checker->isWhitelisted('10.0.0.1'))->toBeFalse();
});

it('matches partial wildcard pattern', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', ['10.0.1.*']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('10.0.1.50'))->toBeTrue();
    expect($checker->isWhitelisted('10.0.2.50'))->toBeFalse();
});

it('matches wildcard in blacklist', function () {
    config()->set('gupa.blacklist.enabled', true);
    config()->set('gupa.blacklist.ips', ['10.99.*.*']);

    $checker = app(WhitelistChecker::class);

    expect($checker->isBlacklisted('10.99.1.1'))->toBeTrue();
    expect($checker->isBlacklisted('10.99.255.255'))->toBeTrue();
    expect($checker->isBlacklisted('10.98.1.1'))->toBeFalse();
});

it('matches combined exact, CIDR, and wildcard', function () {
    config()->set('gupa.whitelist.enabled', true);
    config()->set('gupa.whitelist.ips', [
        '1.2.3.4',
        '10.0.0.0/8',
        '192.168.*.*',
    ]);

    $checker = app(WhitelistChecker::class);

    expect($checker->isWhitelisted('1.2.3.4'))->toBeTrue();
    expect($checker->isWhitelisted('10.0.0.1'))->toBeTrue();
    expect($checker->isWhitelisted('192.168.5.5'))->toBeTrue();
    expect($checker->isWhitelisted('172.16.0.1'))->toBeFalse();
});

it('matchesCidr directly', function () {
    $checker = app(WhitelistChecker::class);

    expect($checker->matchesCidr('192.168.1.10', '192.168.1.0/24'))->toBeTrue();
    expect($checker->matchesCidr('192.168.2.10', '192.168.1.0/24'))->toBeFalse();
    expect($checker->matchesCidr('10.0.0.1', '10.0.0.0/8'))->toBeTrue();
    expect($checker->matchesCidr('bad-ip', '10.0.0.0/8'))->toBeFalse();
});

it('matchesWildcard directly', function () {
    $checker = app(WhitelistChecker::class);

    expect($checker->matchesWildcard('192.168.1.1', '192.168.*.*'))->toBeTrue();
    expect($checker->matchesWildcard('192.168.1.1', '192.168.1.*'))->toBeTrue();
    expect($checker->matchesWildcard('192.168.1.1', '10.0.*.*'))->toBeFalse();
});
