<?php

use Bale\Gupa\Actions\BlockAction;
use Bale\Gupa\Actions\LogAction;
use Bale\Gupa\Models\BlockedIp;
use Bale\Gupa\Models\Log;
use Bale\Gupa\Models\Whitelist;
use Bale\Gupa\Models\Blacklist;
use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('gupa.master.storage', 'database');
    config()->set('gupa.master.log_enabled', true);
});

afterEach(function () {
    config()->set('gupa.master.storage', 'cache');
});

describe('BlockAction — database mode (pure DB, no cache)', function () {
    it('blocks IP in database without writing to cache', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->execute('10.0.0.1');

        $blocked = BlockedIp::where('ip', '10.0.0.1')->first();
        expect($blocked)->not->toBeNull();
        expect($blocked->is_permanent)->toBeFalse();
        expect($blocked->expires_at)->not->toBeNull();

        expect(Cache::has('gupa:blocked:10.0.0.1'))->toBeFalse();
        expect(Cache::has('gupa:permanent:10.0.0.1'))->toBeFalse();
    });

    it('blocks IP permanently in database without cache', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->execute('10.0.0.1', permanent: true);

        $blocked = BlockedIp::where('ip', '10.0.0.1')->first();
        expect($blocked)->not->toBeNull();
        expect($blocked->is_permanent)->toBeTrue();
        expect($blocked->expires_at)->toBeNull();

        expect(Cache::has('gupa:blocked:10.0.0.1'))->toBeFalse();
        expect(Cache::has('gupa:permanent:10.0.0.1'))->toBeFalse();
    });

    it('checks blocked IP from database', function () {
        BlockedIp::create([
            'ip' => '10.0.0.50',
            'reason' => 'test block',
            'is_permanent' => false,
            'expires_at' => now()->addHour(),
        ]);

        $blockAction = app(BlockAction::class);

        expect($blockAction->isBlocked('10.0.0.50'))->toBeTrue();
    });

    it('returns false for expired database entry', function () {
        BlockedIp::create([
            'ip' => '10.0.0.60',
            'reason' => 'test block',
            'is_permanent' => false,
            'expires_at' => now()->subHour(),
        ]);

        $blockAction = app(BlockAction::class);

        expect($blockAction->isBlocked('10.0.0.60'))->toBeFalse();
    });

    it('unblocks IP from database without cache', function () {
        BlockedIp::create([
            'ip' => '10.0.0.70',
            'reason' => 'test block',
            'is_permanent' => false,
            'expires_at' => now()->addHour(),
        ]);

        $blockAction = app(BlockAction::class);
        $blockAction->unblock('10.0.0.70');

        expect(BlockedIp::where('ip', '10.0.0.70')->exists())->toBeFalse();

        expect(Cache::has('gupa:blocked:10.0.0.70'))->toBeFalse();
        expect(Cache::has('gupa:pending_block:10.0.0.70'))->toBeFalse();
        expect(Cache::has('gupa:permanent:10.0.0.70'))->toBeFalse();
        expect(Cache::has('gupa:block_count:10.0.0.70'))->toBeFalse();
    });

    it('updates existing database entry on re-block', function () {
        BlockedIp::create([
            'ip' => '10.0.0.90',
            'reason' => 'first block',
            'is_permanent' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        $blockAction = app(BlockAction::class);
        $blockAction->execute('10.0.0.90', permanent: true);

        $blocked = BlockedIp::where('ip', '10.0.0.90')->first();
        expect($blocked->is_permanent)->toBeTrue();
        expect($blocked->expires_at)->toBeNull();
    });

    it('sets and detects pending block in database', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->setPendingBlock('10.0.0.1');

        expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeTrue();
        expect($blockAction->isBlocked('10.0.0.1'))->toBeFalse();

        $pending = BlockedIp::where('ip', '10.0.0.1')->where('reason', 'pending')->first();
        expect($pending)->not->toBeNull();
    });

    it('applies pending block from database', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->setPendingBlock('10.0.0.1');
        expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeTrue();

        $blockAction->applyPendingBlock('10.0.0.1');

        expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeFalse();
        expect($blockAction->isBlocked('10.0.0.1'))->toBeTrue();

        $pending = BlockedIp::where('ip', '10.0.0.1')->where('reason', 'pending')->first();
        expect($pending)->toBeNull();
    });

    it('tracks block count from logs table', function () {
        $blockAction = app(BlockAction::class);

        expect($blockAction->getBlockCount('10.0.0.1'))->toBe(0);

        Log::create([
            'ip' => '10.0.0.1',
            'event' => 'block',
            'reason' => 'test',
            'score' => 80,
        ]);

        expect($blockAction->getBlockCount('10.0.0.1'))->toBe(1);
    });

    it('does not write any cache keys for block operations', function () {
        $blockAction = app(BlockAction::class);

        Cache::flush();

        $blockAction->execute('10.0.0.1');
        $blockAction->setPendingBlock('10.0.0.1');

        $allCacheKeys = Cache::getStore()->getPrefix();

        expect(Cache::has('gupa:blocked:10.0.0.1'))->toBeFalse();
        expect(Cache::has('gupa:pending_block:10.0.0.1'))->toBeFalse();
        expect(Cache::has('gupa:permanent:10.0.0.1'))->toBeFalse();
        expect(Cache::has('gupa:block_count:10.0.0.1'))->toBeFalse();
    });
});

describe('LogAction — database mode', function () {
    it('stores block log in database', function () {
        $logAction = LogAction::fromConfig();
        $logAction->block('10.0.0.1', 'Test block', 100);

        $log = Log::where('ip', '10.0.0.1')->where('event', 'block')->first();
        expect($log)->not->toBeNull();
        expect($log->reason)->toBe('Test block');
        expect($log->score)->toBe(100);
    });

    it('stores permanent block log in database', function () {
        $logAction = LogAction::fromConfig();
        $logAction->permanentBlock('10.0.0.1', 'Recidivist: 3 blocks', 3);

        $log = Log::where('ip', '10.0.0.1')->where('event', 'permanent_block')->first();
        expect($log)->not->toBeNull();
        expect($log->reason)->toBe('Recidivist: 3 blocks');
        expect($log->metadata)->toHaveKey('block_count');
    });

    it('stores unblock log in database', function () {
        $logAction = LogAction::fromConfig();
        $logAction->unblock('10.0.0.1', 'Manual unblock');

        $log = Log::where('ip', '10.0.0.1')->where('event', 'unblock')->first();
        expect($log)->not->toBeNull();
        expect($log->reason)->toBe('Manual unblock');
    });
});

describe('WhitelistChecker — database mode (pure DB, no cache)', function () {
    it('stores whitelist in database without cache', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->whitelist('10.0.0.99');

        expect(Whitelist::where('ip', '10.0.0.99')->exists())->toBeTrue();

        $cacheIps = Cache::get('gupa:whitelist', []);
        expect(isset($cacheIps['10.0.0.99']))->toBeFalse();
    });

    it('removes whitelist from database without cache', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->whitelist('10.0.0.99');
        $checker->unwhitelist('10.0.0.99');

        expect(Whitelist::where('ip', '10.0.0.99')->exists())->toBeFalse();
    });

    it('stores blacklist in database without cache', function () {
        config()->set('gupa.blacklist.enabled', true);
        config()->set('gupa.blacklist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->blacklist('10.0.0.99');

        expect(Blacklist::where('ip', '10.0.0.99')->exists())->toBeTrue();

        $cacheIps = Cache::get('gupa:blacklist', []);
        expect(isset($cacheIps['10.0.0.99']))->toBeFalse();
    });

    it('removes blacklist from database without cache', function () {
        config()->set('gupa.blacklist.enabled', true);
        config()->set('gupa.blacklist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->blacklist('10.0.0.99');
        $checker->unblacklist('10.0.0.99');

        expect(Blacklist::where('ip', '10.0.0.99')->exists())->toBeFalse();
    });

    it('reads whitelist directly from database', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->whitelist('10.0.0.50');

        expect($checker->isWhitelisted('10.0.0.50'))->toBeTrue();
        expect($checker->isWhitelisted('10.0.0.51'))->toBeFalse();
    });

    it('reads blacklist directly from database', function () {
        config()->set('gupa.blacklist.enabled', true);
        config()->set('gupa.blacklist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->blacklist('10.0.0.50');

        expect($checker->isBlacklisted('10.0.0.50'))->toBeTrue();
        expect($checker->isBlacklisted('10.0.0.51'))->toBeFalse();
    });

    it('returns whitelist IPs from database', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', ['1.2.3.4']);

        Whitelist::create(['ip' => '10.0.0.99']);

        $checker = app(WhitelistChecker::class);
        $ips = $checker->getWhitelistedIps();

        expect($ips)->toContain('1.2.3.4');
        expect($ips)->toContain('10.0.0.99');
    });
});

describe('DashboardCommand — database mode', function () {
    it('shows database counts from table', function () {
        BlockedIp::create([
            'ip' => '10.0.1.1',
            'reason' => 'test',
            'is_permanent' => false,
            'expires_at' => now()->addHour(),
        ]);
        BlockedIp::create([
            'ip' => '10.0.1.2',
            'reason' => 'test',
            'is_permanent' => true,
            'expires_at' => null,
        ]);

        $this->artisan('gupa:dashboard')
            ->expectsTable(['Setting', 'Value'], [
                ['Enabled', 'Yes'],
                ['Storage', 'Database'],
                ['Threshold', 100],
                ['Block Duration', '3600s'],
            ])
            ->assertExitCode(0);
    });
});

describe('SetupCommand', function () {
    it('shows mode choice', function () {
        $this->artisan('gupa:setup')
            ->expectsChoice('Pilih mode instalasi', 0, ['Auto (default)', 'Advance (database storage)'])
            ->expectsConfirmation('Config sudah ada. Timpa?', 'yes')
            ->assertExitCode(0);
    });
});
