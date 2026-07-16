<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Actions\BlockAction;
use Bale\Gupa\Actions\LogAction;
use Illuminate\Console\Command;

class UnblockCommand extends Command
{
    protected $signature = 'gupa:unblock {ip : IP address to unblock}';

    protected $description = 'Unblock an IP address from Gupa guardian';

    public function handle(BlockAction $blockAction, LogAction $logAction): int
    {
        $ip = $this->argument('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->error("Invalid IP address: {$ip}");

            return self::FAILURE;
        }

        if (!$blockAction->isBlocked($ip)) {
            $this->warn("IP {$ip} is not currently blocked.");

            return self::SUCCESS;
        }

        $blockAction->unblock($ip);
        $logAction->unblock($ip, 'Manual unblock via artisan');

        $this->info("IP {$ip} has been unblocked successfully.");

        return self::SUCCESS;
    }
}
