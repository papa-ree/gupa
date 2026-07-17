<?php

namespace Bale\Gupa\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SetupCommand extends Command
{
    protected $signature = 'gupa:setup';

    protected $description = 'Setup Gupa package (auto or advance mode with database storage)';

    private string $basePath;

    public function handle(): int
    {
        $this->basePath = base_path();

        $this->info('');
        $this->info(' ____            _                    ____                 _ ');
        $this->info('|  _ \  __ _ ___| |_ ___  _ __ _   _ / ___| ___  _ __   __| |');
        $this->info('| |_) |/ _` / __| __/ _ \| \'__| | | | |  _ / _ \| \'_ \ / _` |');
        $this->info('|  __/| (_| \__ \ || (_) | |  | |_| | |_| | (_) | | | | (_| |');
        $this->info('|_|    \__,_|___/\__\___/|_|   \__, |\____|\___/|_| |_|\__,_|');
        $this->info('                               |___/                         ');
        $this->newLine();

        $mode = $this->choice(
            'Pilih mode instalasi',
            ['Auto (default)', 'Advance (database storage)'],
            0
        );

        $this->newLine();

        $this->line('Mode: <info>' . ($mode === 0 ? 'auto' : 'advance') . '</info>');
        $this->newLine();

        if ($mode === 0) {
            return $this->setupAuto();
        }

        return $this->setupAdvance();
    }

    private function setupAuto(): int
    {
        $this->line('<comment>[1/3]</comment> Publishing config...');
        $this->publishConfig();
        $this->newLine();

        $this->line('<comment>[2/3]</comment> Adding env vars to .env...');
        $this->addEnvVars();
        $this->newLine();

        $this->line('<comment>[3/3]</comment> Checking middleware registration...');
        $this->checkMiddleware();
        $this->newLine();

        $this->info('Setup selesai!');
        $this->newLine();
        $this->line('Jalankan <info>php artisan config:cache</info> untuk menerapkan perubahan.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function setupAdvance(): int
    {
        $total = 5;

        $this->line('<comment>[1/' . $total . ']</comment> Publishing config...');
        $this->publishConfig();
        $this->newLine();

        $this->line('<comment>[2/' . $total . ']</comment> Adding env vars to .env...');
        $this->addEnvVars(withStorage: true);
        $this->newLine();

        $this->line('<comment>[3/' . $total . ']</comment> Checking middleware registration...');
        $this->checkMiddleware();
        $this->newLine();

        $this->line('<comment>[4/' . $total . ']</comment> Publishing migrations...');
        $this->publishMigrations();
        $this->newLine();

        $this->line('<comment>[5/' . $total . ']</comment> Running migrations...');
        if (!$this->checkDatabaseConnection()) {
            $this->warn('  Migration dilewati — periksa koneksi database Anda.');
            $this->newLine();

            return $this->finishAdvanceSkipped();
        }
        $this->call('migrate');
        $this->newLine();

        $this->info('Setup selesai (advance mode)!');
        $this->newLine();
        $this->line('Jalankan <info>php artisan config:cache</info> untuk menerapkan perubahan.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $source = $this->packagePath('config/gupa.php');
        $destination = config_path('gupa.php');

        if (File::exists($destination) && !$this->confirm('Config sudah ada. Timpa?', false)) {
            $this->warn('Skipping config publish.');

            return;
        }

        if (!is_dir(config_path())) {
            @mkdir(config_path(), 0755, true);
        }

        File::copy($source, $destination);
        $this->info("  Published: {$destination}");
    }

    private function addEnvVars(bool $withStorage = false): void
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->warn('  .env file tidak ditemukan. Silakan tambahkan env vars manual.');

            return;
        }

        $env = File::get($envPath);

        $vars = [
            'GUPA_ENABLED=true',
            'GUPA_THRESHOLD=100',
            'GUPA_SCORE_DECAY_WINDOW=300',
            'GUPA_BLOCK_DURATION=3600',
            'GUPA_LOG_ENABLED=true',
        ];

        if ($withStorage) {
            $vars[] = 'GUPA_STORAGE=database';
        }

        $added = 0;

        foreach ($vars as $var) {
            $key = explode('=', $var)[0];

            if (str_contains($env, $key)) {
                continue;
            }

            $env .= "\n{$var}";
            $added++;
        }

        if ($added > 0) {
            File::put($envPath, $env);
            $this->info("  Added {$added} env vars to .env");
        } else {
            $this->info('  All env vars already present');
        }
    }

    private function checkMiddleware(): void
    {
        $bootstrapApp = $this->basePath . '/bootstrap/app.php';

        if (!File::exists($bootstrapApp)) {
            $this->warn('  bootstrap/app.php tidak ditemukan.');

            return;
        }

        $content = File::get($bootstrapApp);
        $middlewareClass = 'Bale\\Gupa\\Middleware\\GuardianMiddleware';

        if (str_contains($content, 'GuardianMiddleware')) {
            $this->info('  Middleware already registered');

            return;
        }

        $this->warn('  GuardianMiddleware belum terdaftar di bootstrap/app.php');
        $this->line('  Tambahkan manual:');
        $this->line("  <info>->withMiddleware(function (Middleware \$middleware) {");
        $this->line("      \$middleware->prepend({$middlewareClass}::class);");
        $this->line("  })</info>");
    }

    private function publishMigrations(): void
    {
        $source = $this->packagePath('database/migrations');
        $destination = database_path('migrations');

        if (!is_dir($source)) {
            $this->warn('  Migration directory not found');

            return;
        }

        if (!is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }

        $files = glob($source . '/*.php');
        $published = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            $destFile = $destination . '/' . $basename;

            if (File::exists($destFile)) {
                continue;
            }

            File::copy($file, $destFile);
            $published++;
        }

        $this->info("  Published {$published} migration(s)");
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            $this->error('  Database connection gagal: ' . $e->getMessage());

            return false;
        }
    }

    private function finishAdvanceSkipped(): int
    {
        $this->info('Setup selesai (advance mode) — migrate dilewati.');
        $this->newLine();
        $this->line('Setelah DB siap, jalankan: <info>php artisan migrate</info>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function packagePath(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relativePath;

        if (!File::exists($path)) {
            $path = $this->basePath . '/vendor/bale/gupa/' . $relativePath;
        }

        return $path;
    }
}
