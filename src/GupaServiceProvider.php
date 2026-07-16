<?php

namespace Bale\Gupa;

use Bale\Gupa\Actions\BlockAction;
use Bale\Gupa\Actions\LogAction;
use Bale\Gupa\Actions\NotifyAction;
use Bale\Gupa\Commands\BlacklistCommand;
use Bale\Gupa\Commands\ClearScoreCommand;
use Bale\Gupa\Commands\DashboardCommand;
use Bale\Gupa\Commands\StatsCommand;
use Bale\Gupa\Commands\UnblockCommand;
use Bale\Gupa\Commands\WhitelistCommand;
use Bale\Gupa\Detectors\HeaderDetector;
use Bale\Gupa\Detectors\HoneypotDetector;
use Bale\Gupa\Detectors\NotFoundDetector;
use Bale\Gupa\Detectors\RateLimitDetector;
use Bale\Gupa\Detectors\VelocityDetector;
use Bale\Gupa\Middleware\GuardianMiddleware;
use Bale\Gupa\Scorer\ScoreCalculator;
use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Support\ServiceProvider;

class GupaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gupa.php', 'gupa');

        $this->app->singleton(WhitelistChecker::class);
        $this->app->singleton(BlockAction::class);
        $this->app->singleton(ScoreCalculator::class);
        $this->app->singleton(LogAction::class, function () {
            return LogAction::fromConfig();
        });
        $this->app->singleton(NotifyAction::class, function () {
            return NotifyAction::fromConfig();
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMiddleware();
        $this->registerDetectors();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    private function registerPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/gupa.php' => config_path('gupa.php'),
        ], 'gupa:config');
    }

    private function registerMiddleware(): void
    {
        $router = $this->app['router'];

        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('gupa', GuardianMiddleware::class);
        } else {
            $router->prependMiddlewareToGroup('web', GuardianMiddleware::class);
        }
    }

    private function registerDetectors(): void
    {
        $calculator = $this->app->make(ScoreCalculator::class);

        $calculator->register(new VelocityDetector());
        $calculator->register(new HoneypotDetector());
        $calculator->register(new HeaderDetector());
        $calculator->register(new NotFoundDetector());
        $calculator->register(new RateLimitDetector());
    }

    private function registerCommands(): void
    {
        $this->commands([
            UnblockCommand::class,
            StatsCommand::class,
            DashboardCommand::class,
            ClearScoreCommand::class,
            WhitelistCommand::class,
            BlacklistCommand::class,
        ]);
    }
}
