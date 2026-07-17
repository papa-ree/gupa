<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Console\Command;

class WhitelistCommand extends Command
{
    protected $signature = 'gupa:whitelist
        {--add= : IP or CIDR to add to whitelist}
        {--remove= : IP or CIDR to remove from whitelist}
        {--list : List all whitelisted IPs}';

    protected $description = 'Manage dynamic whitelist entries';

    public function handle(WhitelistChecker $whitelistChecker): int
    {
        if ($this->option('add')) {
            return $this->addToWhitelist($whitelistChecker);
        }

        if ($this->option('remove')) {
            return $this->removeFromWhitelist($whitelistChecker);
        }

        return $this->listWhitelist($whitelistChecker);
    }

    private function addToWhitelist(WhitelistChecker $whitelistChecker): int
    {
        $ip = $this->option('add');

        if (in_array($ip, $whitelistChecker->getBlacklistedIps())) {
            $whitelistChecker->unblacklist($ip);
            $this->comment("  Removed {$ip} from blacklist (conflict resolved).");
        }

        $whitelistChecker->whitelist($ip);
        $this->info("Added {$ip} to dynamic whitelist.");

        $storage = config('gupa.master.storage') === 'database' ? 'database + cache' : 'cache';
        $this->comment("  Storage: {$storage}");

        return self::SUCCESS;
    }

    private function removeFromWhitelist(WhitelistChecker $whitelistChecker): int
    {
        $ip = $this->option('remove');

        $whitelistChecker->unwhitelist($ip);
        $this->info("Removed {$ip} from dynamic whitelist.");

        return self::SUCCESS;
    }

    private function listWhitelist(WhitelistChecker $whitelistChecker): int
    {
        $ips = $whitelistChecker->getWhitelistedIps();

        if (empty($ips)) {
            $this->comment('No whitelisted IPs found.');

            return self::SUCCESS;
        }

        $this->info('Whitelisted IPs:');
        $this->newLine();

        foreach ($ips as $ip) {
            $this->line("  - {$ip}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
