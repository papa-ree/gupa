<?php

namespace Bale\Gupa\Actions;

use Illuminate\Support\Facades\Log;

class LogAction
{
    public function __construct(
        private bool $enabled,
    ) {}

    public static function fromConfig(): self
    {
        return new self((bool) config('gupa.master.log_enabled', true));
    }

    public function block(string $ip, string $reason, int $score): void
    {
        if (!$this->enabled) {
            return;
        }

        Log::warning('Gupa: IP blocked', [
            'ip' => $ip,
            'reason' => $reason,
            'score' => $score,
            'threshold' => config('gupa.master.threshold', 100),
            'block_duration' => config('gupa.master.block_duration', 3600),
        ]);
    }

    public function permanentBlock(string $ip, string $reason, int $blockCount): void
    {
        if (!$this->enabled) {
            return;
        }

        Log::critical('Gupa: IP permanently blocked (recidivist)', [
            'ip' => $ip,
            'reason' => $reason,
            'block_count' => $blockCount,
        ]);
    }

    public function unblock(string $ip, string $reason): void
    {
        if (!$this->enabled) {
            return;
        }

        Log::info('Gupa: IP unblocked', [
            'ip' => $ip,
            'reason' => $reason,
        ]);
    }
}
