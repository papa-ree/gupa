<?php

namespace Bale\Gupa\Actions;

use Bale\Gupa\Models\BlockedIp as BlockedIpModel;
use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Support\Facades\Cache;

class BlockAction
{
    private const BLOCK_KEY_PREFIX = 'gupa:blocked:';
    private const PENDING_KEY_PREFIX = 'gupa:pending_block:';
    private const BLOCK_COUNT_KEY_PREFIX = 'gupa:block_count:';
    private const PERMANENT_KEY_PREFIX = 'gupa:permanent:';

    public function __construct(
        private WhitelistChecker $whitelistChecker,
    ) {}

    public function isBlocked(string $ip): bool
    {
        if ($this->useDatabase()) {
            return $this->isBlockedInDatabase($ip);
        }

        return Cache::has(self::BLOCK_KEY_PREFIX . $ip)
            || Cache::has(self::PERMANENT_KEY_PREFIX . $ip);
    }

    public function execute(string $ip, bool $permanent = false): void
    {
        $this->whitelistChecker->unwhitelist($ip);

        if ($permanent) {
            Cache::forever(self::BLOCK_KEY_PREFIX . $ip, true);
            Cache::forever(self::PERMANENT_KEY_PREFIX . $ip, true);
        } else {
            $duration = config('gupa.master.block_duration', 3600);
            Cache::put(self::BLOCK_KEY_PREFIX . $ip, true, $duration);
        }

        if ($this->useDatabase()) {
            $this->storeInDatabase($ip, $permanent);
        }

        $this->recordBlock($ip);
    }

    public function unblock(string $ip): void
    {
        Cache::forget(self::BLOCK_KEY_PREFIX . $ip);
        Cache::forget(self::PENDING_KEY_PREFIX . $ip);
        Cache::forget(self::PERMANENT_KEY_PREFIX . $ip);
        Cache::forget(self::BLOCK_COUNT_KEY_PREFIX . $ip);

        if ($this->useDatabase()) {
            $this->removeFromDatabase($ip);
        }
    }

    public function setPendingBlock(string $ip): void
    {
        $duration = config('gupa.master.block_duration', 3600);
        Cache::put(self::PENDING_KEY_PREFIX . $ip, true, $duration);
    }

    public function hasPendingBlock(string $ip): bool
    {
        return Cache::has(self::PENDING_KEY_PREFIX . $ip);
    }

    public function applyPendingBlock(string $ip): void
    {
        if ($this->hasPendingBlock($ip)) {
            Cache::forget(self::PENDING_KEY_PREFIX . $ip);
            $this->execute($ip);
        }
    }

    public function recordBlock(string $ip): void
    {
        $key = self::BLOCK_COUNT_KEY_PREFIX . $ip;
        $window = 86400; // 24 jam

        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, $window);
    }

    public function getBlockCount(string $ip): int
    {
        return (int) Cache::get(self::BLOCK_COUNT_KEY_PREFIX . $ip, 0);
    }

    public function resetBlockCount(string $ip): void
    {
        Cache::forget(self::BLOCK_COUNT_KEY_PREFIX . $ip);
    }

    private function useDatabase(): bool
    {
        return config('gupa.master.storage') === 'database';
    }

    private function isBlockedInDatabase(string $ip): bool
    {
        $query = BlockedIpModel::where('ip', $ip)->notExpired();

        return $query->exists();
    }

    private function storeInDatabase(string $ip, bool $permanent): void
    {
        $duration = config('gupa.master.block_duration', 3600);

        BlockedIpModel::updateOrCreate(
            ['ip' => $ip],
            [
                'reason' => 'Score threshold exceeded',
                'is_permanent' => $permanent,
                'expires_at' => $permanent ? null : now()->addSeconds($duration),
            ]
        );
    }

    private function removeFromDatabase(string $ip): void
    {
        BlockedIpModel::where('ip', $ip)->delete();
    }
}
