<?php

use Bale\Gupa\Actions\LogAction;
use Bale\Gupa\Models\Log as LogModel;
use Illuminate\Http\Request;

beforeEach(function () {
    config()->set('gupa.master.storage', 'database');
    config()->set('gupa.master.log_enabled', true);
    config()->set('gupa.master.suspicious_threshold', 10);
});

afterEach(function () {
    config()->set('gupa.master.storage', 'cache');
});

describe('LogAction — suspicious request logging', function () {
    it('logs suspicious request when score meets threshold', function () {
        $logAction = LogAction::fromConfig();
        $request = Request::create('/wp-admin.php', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'python-requests/2.28',
        ]);

        $logAction->logSuspiciousRequest($request, 15);

        $log = LogModel::fromIp('10.0.0.1')->requests()->first();
        expect($log)->not->toBeNull();
        expect($log->path)->toBe('wp-admin.php');
        expect($log->method)->toBe('GET');
        expect($log->user_agent)->toBe('python-requests/2.28');
        expect($log->score)->toBe(15);
    });

    it('does not log when score is below suspicious threshold', function () {
        config()->set('gupa.master.suspicious_threshold', 50);

        $logAction = LogAction::fromConfig();
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.2',
        ]);

        $logAction->logSuspiciousRequest($request, 20);

        $log = LogModel::fromIp('10.0.0.2')->requests()->first();
        expect($log)->toBeNull();
    });

    it('does not log when storage is cache mode', function () {
        config()->set('gupa.master.storage', 'cache');

        $logAction = LogAction::fromConfig();
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.3',
        ]);

        $logAction->logSuspiciousRequest($request, 15);

        $log = LogModel::fromIp('10.0.0.3')->requests()->first();
        expect($log)->toBeNull();
    });

    it('logs multiple suspicious requests for same IP', function () {
        $logAction = LogAction::fromConfig();

        $request1 = Request::create('/wp-admin.php', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.4']);
        $logAction->logSuspiciousRequest($request1, 20);

        $request2 = Request::create('/xmlrpc.php', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.4']);
        $logAction->logSuspiciousRequest($request2, 35);

        $request3 = Request::create('/wp-login.php', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.4']);
        $logAction->logSuspiciousRequest($request3, 55);

        $logs = LogModel::fromIp('10.0.0.4')->requests()->get();
        expect($logs)->toHaveCount(3);
        expect($logs->pluck('path')->toArray())->toBe([
            'wp-admin.php',
            'xmlrpc.php',
            'wp-login.php',
        ]);
        expect($logs->pluck('score')->toArray())->toBe([20, 35, 55]);
    });

    it('logs POST method correctly', function () {
        $logAction = LogAction::fromConfig();
        $request = Request::create('/xmlrpc.php', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
        ]);

        $logAction->logSuspiciousRequest($request, 15);

        $log = LogModel::fromIp('10.0.0.5')->requests()->first();
        expect($log->method)->toBe('POST');
    });

    it('preserves block event logs alongside request logs', function () {
        $logAction = LogAction::fromConfig();

        $request1 = Request::create('/wp-admin.php', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.6']);
        $logAction->logSuspiciousRequest($request1, 20);

        $request2 = Request::create('/xmlrpc.php', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.6']);
        $logAction->logSuspiciousRequest($request2, 40);

        $logAction->block('10.0.0.6', 'Score threshold exceeded: 60', 60);

        $allLogs = LogModel::fromIp('10.0.0.6')->get();
        expect($allLogs)->toHaveCount(3);

        $requests = $allLogs->where('event', 'request');
        expect($requests)->toHaveCount(2);

        $blocks = $allLogs->where('event', 'block');
        expect($blocks)->toHaveCount(1);
    });
});

describe('LogCommand', function () {
    it('shows no logs message when empty', function () {
        $this->artisan('gupa:log', ['--ip' => '10.0.0.99'])
            ->expectsOutput('No logs found for IP 10.0.0.99 in the last 30 days.')
            ->assertExitCode(0);
    });

    it('prunes old logs', function () {
        LogModel::create([
            'ip' => '10.0.0.1',
            'event' => 'request',
            'reason' => 'test',
            'score' => 20,
            'path' => '/test',
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('gupa:log', ['--prune' => true])
            ->expectsOutput('Pruned 1 log(s) older than 30 days.')
            ->assertExitCode(0);

        expect(LogModel::where('ip', '10.0.0.1')->count())->toBe(0);
    });

    it('shows recent logs', function () {
        LogModel::create([
            'ip' => '10.0.0.1',
            'event' => 'request',
            'reason' => 'test',
            'score' => 20,
            'path' => '/wp-admin.php',
            'method' => 'GET',
            'status_code' => null,
            'created_at' => now(),
        ]);

        $this->artisan('gupa:log')
            ->expectsOutput('Recent logs')
            ->assertExitCode(0);
    });

    it('filters by status code', function () {
        LogModel::create([
            'ip' => '10.0.0.1',
            'event' => 'request',
            'reason' => 'test',
            'score' => 20,
            'path' => '/not-found',
            'status_code' => 404,
            'created_at' => now(),
        ]);

        $this->artisan('gupa:log', ['--status' => 404])
            ->assertExitCode(0);
    });
});
