<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Actions\BlockAction;
use Bale\Gupa\Scorer\ScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ClearScoreCommand extends Command
{
    protected $signature = 'gupa:clear-score {ip : IP address to clear score for}';

    protected $description = 'Clear the accumulated score for an IP address';

    public function handle(BlockAction $blockAction, ScoreCalculator $scoreCalculator): int
    {
        $ip = $this->argument('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->error("Invalid IP address: {$ip}");

            return self::FAILURE;
        }

        $request = Request::create('/dummy', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        $previousScore = $scoreCalculator->getTotalScore($request);
        $scoreCalculator->resetScore($request);

        $this->info("Score cleared for IP {$ip} (was: {$previousScore})");

        return self::SUCCESS;
    }
}
