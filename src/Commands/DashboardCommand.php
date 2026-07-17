<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Models\BlockedIp as BlockedIpModel;
use Bale\Gupa\Scorer\ScoreCalculator;
use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DashboardCommand extends Command
{
    protected $signature = 'gupa:dashboard {--json : Output as JSON}';

    protected $description = 'Show Gupa guardian dashboard with stats and activity';

    public function handle(ScoreCalculator $scoreCalculator, WhitelistChecker $whitelistChecker): int
    {
        $useDb = config('gupa.master.storage') === 'database';

        $blockedCount = $useDb ? $this->countBlockedInDatabase() : $this->countCacheKeys('gupa:blocked:*');
        $pendingCount = $useDb ? 0 : $this->countCacheKeys('gupa:pending_block:*');
        $permanentCount = $useDb ? $this->countPermanentInDatabase() : $this->countCacheKeys('gupa:permanent:*');
        $scoreCount = $this->countCacheKeys('gupa:score:*');

        $data = [
            'enabled' => config('gupa.master.enabled', true),
            'storage' => $useDb ? 'database' : 'cache',
            'threshold' => config('gupa.master.threshold', 100),
            'block_duration' => config('gupa.master.block_duration', 3600),
            'stats' => [
                'blocked_ips' => $blockedCount,
                'pending_blocks' => $pendingCount,
                'permanent_blocks' => $permanentCount,
                'tracked_scores' => $scoreCount,
            ],
            'detectors' => $scoreCalculator->getActiveDetectors(),
            'whitelist_ips' => $whitelistChecker->getWhitelistedIps(),
            'blacklist_ips' => $whitelistChecker->getBlacklistedIps(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Gupa Guardian Dashboard');
        $this->newLine();

        $this->table(['Setting', 'Value'], [
            ['Enabled', $data['enabled'] ? 'Yes' : 'No'],
            ['Storage', ucfirst($data['storage'])],
            ['Threshold', $data['threshold']],
            ['Block Duration', $data['block_duration'] . 's'],
        ]);

        $this->newLine();
        $this->info('Activity');
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Blocked IPs', $data['stats']['blocked_ips']],
            ['Pending Blocks', $data['stats']['pending_blocks']],
            ['Permanent Blocks', $data['stats']['permanent_blocks']],
            ['Tracked Scores', $data['stats']['tracked_scores']],
        ]);

        $this->newLine();
        $this->info('Active Detectors');
        $this->newLine();

        if (!empty($data['detectors'])) {
            $this->line('  ' . implode(', ', $data['detectors']));
        } else {
            $this->comment('  None');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function countBlockedInDatabase(): int
    {
        try {
            return BlockedIpModel::notExpired()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countPermanentInDatabase(): int
    {
        try {
            return BlockedIpModel::where('is_permanent', true)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countCacheKeys(string $prefix): int
    {
        $store = Cache::getStore();

        if (method_exists($store, 'getRedis')) {
            return $this->countViaRedis($prefix);
        }

        return $this->countViaStore($prefix);
    }

    private function countViaRedis(string $prefix): int
    {
        try {
            $store = Cache::getStore();
            $redisPrefix = method_exists($store, 'getPrefix') ? $store->getPrefix() : '';
            $fullPrefix = $redisPrefix ? $redisPrefix . $prefix : $prefix;

            $redis = $store->getRedis();
            $connection = $redis->connection();
            $keys = $connection->keys($fullPrefix);

            return is_array($keys) ? count($keys) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countViaStore(string $prefix): int
    {
        try {
            $path = storage_path('framework/cache/data');

            if (!is_dir($path)) {
                return 0;
            }

            $files = glob($path . '/*');
            $count = 0;

            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== false && str_contains($content, str_replace('*', '', $prefix))) {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }
}
