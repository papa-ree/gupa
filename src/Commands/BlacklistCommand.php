<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Support\WhitelistChecker;
use Illuminate\Console\Command;

class BlacklistCommand extends Command
{
    protected $signature = 'gupa:blacklist
        {--add= : IP or CIDR to add to blacklist}
        {--remove= : IP or CIDR to remove from blacklist}
        {--list : List all blacklisted IPs}';

    protected $description = 'Manage dynamic blacklist entries';

    public function handle(WhitelistChecker $whitelistChecker): int
    {
        if ($this->option('add')) {
            return $this->addToBlacklist($whitelistChecker);
        }

        if ($this->option('remove')) {
            return $this->removeFromBlacklist($whitelistChecker);
        }

        return $this->listBlacklist($whitelistChecker);
    }

    private function addToBlacklist(WhitelistChecker $whitelistChecker): int
    {
        $ip = $this->option('add');

        $whitelistChecker->blacklist($ip);
        $this->info("Added {$ip} to dynamic blacklist.");

        $storage = config('gupa.master.storage') === 'database' ? 'database + cache' : 'cache';
        $this->comment("  Storage: {$storage}");

        return self::SUCCESS;
    }

    private function removeFromBlacklist(WhitelistChecker $whitelistChecker): int
    {
        $ip = $this->option('remove');

        $whitelistChecker->unblacklist($ip);
        $this->info("Removed {$ip} from dynamic blacklist.");

        return self::SUCCESS;
    }

    private function listBlacklist(WhitelistChecker $whitelistChecker): int
    {
        $ips = $whitelistChecker->getBlacklistedIps();

        if (empty($ips)) {
            $this->comment('No blacklisted IPs found.');

            return self::SUCCESS;
        }

        $this->info('Blacklisted IPs:');
        $this->newLine();

        foreach ($ips as $ip) {
            $this->line("  - {$ip}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
