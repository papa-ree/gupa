<?php

namespace Bale\Gupa\Actions;

use Bale\Gupa\Models\Log as LogModel;
use Illuminate\Http\Request;
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

        if ($this->useDatabase()) {
            $this->storeLog($ip, 'block', $reason, $score, metadata: [
                'threshold' => config('gupa.master.threshold', 100),
                'block_duration' => config('gupa.master.block_duration', 3600),
            ]);
        }
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

        if ($this->useDatabase()) {
            $this->storeLog($ip, 'permanent_block', $reason, 0, metadata: [
                'block_count' => $blockCount,
            ]);
        }
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

        if ($this->useDatabase()) {
            $this->storeLog($ip, 'unblock', $reason, 0);
        }
    }

    public function logSuspiciousRequest(Request $request, int $score): void
    {
        if (!$this->enabled || !$this->useDatabase()) {
            return;
        }

        $suspiciousThreshold = config('gupa.master.suspicious_threshold', 10);

        if ($score < $suspiciousThreshold) {
            return;
        }

        $this->storeLog(
            $request->ip(),
            'request',
            'Suspicious request detected',
            $score,
            $request->path(),
            $request->method(),
            $request->userAgent(),
            null
        );
    }

    private function useDatabase(): bool
    {
        return config('gupa.master.storage') === 'database';
    }

    private function storeLog(
        string $ip,
        string $event,
        string $reason,
        int $score,
        ?string $path = null,
        ?string $method = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): void {
        LogModel::create([
            'ip' => $ip,
            'event' => $event,
            'reason' => $reason,
            'score' => $score,
            'path' => $path,
            'method' => $method,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }
}
