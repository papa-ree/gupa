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

describe('BlockAction — database mode', function () {
    it('blocks IP in database', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->execute('10.0.0.1');

        $blocked = BlockedIp::where('ip', '10.0.0.1')->first();
        expect($blocked)->not->toBeNull();
        expect($blocked->is_permanent)->toBeFalse();
        expect($blocked->expires_at)->not->toBeNull();
    });

    it('blocks IP permanently in database', function () {
        $blockAction = app(BlockAction::class);

        $blockAction->execute('10.0.0.1', permanent: true);

        $blocked = BlockedIp::where('ip', '10.0.0.1')->first();
        expect($blocked)->not->toBeNull();
        expect($blocked->is_permanent)->toBeTrue();
        expect($blocked->expires_at)->toBeNull();
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

    it('unblocks IP from database', function () {
        BlockedIp::create([
            'ip' => '10.0.0.70',
            'reason' => 'test block',
            'is_permanent' => false,
            'expires_at' => now()->addHour(),
        ]);

        $blockAction = app(BlockAction::class);
        $blockAction->unblock('10.0.0.70');

        expect(BlockedIp::where('ip', '10.0.0.70')->exists())->toBeFalse();
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

describe('WhitelistChecker — database mode', function () {
    it('stores whitelist in database', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->whitelist('10.0.0.99');

        expect(Whitelist::where('ip', '10.0.0.99')->exists())->toBeTrue();
    });

    it('removes whitelist from database', function () {
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->whitelist('10.0.0.99');
        $checker->unwhitelist('10.0.0.99');

        expect(Whitelist::where('ip', '10.0.0.99')->exists())->toBeFalse();
    });

    it('stores blacklist in database', function () {
        config()->set('gupa.blacklist.enabled', true);
        config()->set('gupa.blacklist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->blacklist('10.0.0.99');

        expect(Blacklist::where('ip', '10.0.0.99')->exists())->toBeTrue();
    });

    it('removes blacklist from database', function () {
        config()->set('gupa.blacklist.enabled', true);
        config()->set('gupa.blacklist.ips', []);

        $checker = app(WhitelistChecker::class);
        $checker->blacklist('10.0.0.99');
        $checker->unblacklist('10.0.0.99');

        expect(Blacklist::where('ip', '10.0.0.99')->exists())->toBeFalse();
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
