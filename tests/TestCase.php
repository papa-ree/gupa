<?php

namespace Bale\Gupa\Tests;

use Bale\Gupa\GupaServiceProvider;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GupaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        config()->set('gupa.master.enabled', true);
        config()->set('gupa.master.threshold', 100);
        config()->set('gupa.master.score_decay_window', 300);
        config()->set('gupa.master.block_duration', 3600);
        config()->set('gupa.master.log_enabled', false);
        config()->set('gupa.whitelist.enabled', true);
        config()->set('gupa.whitelist.ips', ['127.0.0.1', '::1']);
        config()->set('gupa.blacklist.enabled', false);
        config()->set('gupa.blacklist.ips', []);

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineRoutes($app): void
    {
        Route::get('/test-guardian', function () {
            return response()->json(['status' => 'ok']);
        })->middleware('gupa');
    }
}
