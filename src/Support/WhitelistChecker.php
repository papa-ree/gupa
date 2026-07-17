<?php

namespace Bale\Gupa\Support;

use Illuminate\Support\Facades\Cache;

class WhitelistChecker
{
    private const WHITELIST_CACHE_KEY = 'gupa:whitelist';
    private const BLACKLIST_CACHE_KEY = 'gupa:blacklist';

    public function isWhitelisted(string $ip): bool
    {
        if (!$this->isWhitelistEnabled()) {
            return false;
        }

        return $this->matchIp($ip, $this->getWhitelistIps());
    }

    public function isBlacklisted(string $ip): bool
    {
        if (!$this->isBlacklistEnabled()) {
            return false;
        }

        return $this->matchIp($ip, $this->getBlacklistIps());
    }

    public function whitelist(string $ip): void
    {
        $ips = Cache::get(self::WHITELIST_CACHE_KEY, []);
        $ips[$ip] = true;
        Cache::forever(self::WHITELIST_CACHE_KEY, $ips);

        if ($this->useDatabase()) {
            $this->storeWhitelistInDatabase($ip);
        }
    }

    public function unwhitelist(string $ip): void
    {
        $ips = Cache::get(self::WHITELIST_CACHE_KEY, []);
        unset($ips[$ip]);
        Cache::forever(self::WHITELIST_CACHE_KEY, $ips);

        if ($this->useDatabase()) {
            $this->removeWhitelistFromDatabase($ip);
        }
    }

    public function blacklist(string $ip): void
    {
        $ips = Cache::get(self::BLACKLIST_CACHE_KEY, []);
        $ips[$ip] = true;
        Cache::forever(self::BLACKLIST_CACHE_KEY, $ips);

        if ($this->useDatabase()) {
            $this->storeBlacklistInDatabase($ip);
        }
    }

    public function unblacklist(string $ip): void
    {
        $ips = Cache::get(self::BLACKLIST_CACHE_KEY, []);
        unset($ips[$ip]);
        Cache::forever(self::BLACKLIST_CACHE_KEY, $ips);

        if ($this->useDatabase()) {
            $this->removeBlacklistFromDatabase($ip);
        }
    }

    public function getWhitelistedIps(): array
    {
        return array_keys($this->getWhitelistIps());
    }

    public function getBlacklistedIps(): array
    {
        return array_keys($this->getBlacklistIps());
    }

    public function syncFromDatabase(): void
    {
        if (!$this->useDatabase()) {
            return;
        }

        $whitelistModel = \Bale\Gupa\Models\Whitelist::class;
        $blacklistModel = \Bale\Gupa\Models\Blacklist::class;

        if (class_exists($whitelistModel)) {
            $dynamicIps = $whitelistModel::pluck('ip')->flip()->toArray();
            Cache::forever(self::WHITELIST_CACHE_KEY, $dynamicIps);
        }

        if (class_exists($blacklistModel)) {
            $dynamicIps = $blacklistModel::pluck('ip')->flip()->toArray();
            Cache::forever(self::BLACKLIST_CACHE_KEY, $dynamicIps);
        }
    }

    private function matchIp(string $ip, array $list): bool
    {
        if (isset($list[$ip])) {
            return true;
        }

        foreach ($list as $entry => $value) {
            if (str_contains($entry, '/')) {
                if ($this->matchesCidr($ip, $entry)) {
                    return true;
                }
            } elseif (str_contains($entry, '*')) {
                if ($this->matchesWildcard($ip, $entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function matchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefix] = explode('/', $cidr, 2);

        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $prefix);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    public function matchesWildcard(string $ip, string $pattern): bool
    {
        $segments = explode('.', $pattern);

        $regexSegments = array_map(function ($segment) {
            if ($segment === '*') {
                return '(\d{1,3})';
            }
            return preg_quote($segment, '/');
        }, $segments);

        $regex = '/^' . implode('\.', $regexSegments) . '$/';

        return (bool) preg_match($regex, $ip);
    }

    private function getWhitelistIps(): array
    {
        $configIps = config('gupa.whitelist.ips', []);
        $dynamicIps = Cache::get(self::WHITELIST_CACHE_KEY, []);

        return array_merge(array_flip($configIps), $dynamicIps);
    }

    private function getBlacklistIps(): array
    {
        $configIps = config('gupa.blacklist.ips', []);
        $dynamicIps = Cache::get(self::BLACKLIST_CACHE_KEY, []);

        return array_merge(array_flip($configIps), $dynamicIps);
    }

    private function isWhitelistEnabled(): bool
    {
        return (bool) config('gupa.whitelist.enabled', true);
    }

    private function isBlacklistEnabled(): bool
    {
        return (bool) config('gupa.blacklist.enabled', false);
    }

    private function useDatabase(): bool
    {
        return config('gupa.master.storage') === 'database';
    }

    private function storeWhitelistInDatabase(string $ip): void
    {
        \Bale\Gupa\Models\Whitelist::firstOrCreate(['ip' => $ip]);
    }

    private function removeWhitelistFromDatabase(string $ip): void
    {
        \Bale\Gupa\Models\Whitelist::where('ip', $ip)->delete();
    }

    private function storeBlacklistInDatabase(string $ip): void
    {
        \Bale\Gupa\Models\Blacklist::firstOrCreate(['ip' => $ip]);
    }

    private function removeBlacklistFromDatabase(string $ip): void
    {
        \Bale\Gupa\Models\Blacklist::where('ip', $ip)->delete();
    }
}
