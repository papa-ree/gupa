<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Models\Log as LogModel;
use Illuminate\Console\Command;

class LogCommand extends Command
{
    protected $signature = 'gupa:log
        {--ip= : Filter by IP address}
        {--status= : Filter by status code (404, etc)}
        {--event= : Filter by event type (request, block, etc)}
        {--days=30 : Show logs from last N days}
        {--prune : Delete logs older than retention period}
        {--json : Output as JSON}';

    protected $description = 'View and manage Gupa request logs';

    public function handle(): int
    {
        if ($this->option('prune')) {
            return $this->pruneLogs();
        }

        if ($this->option('ip')) {
            return $this->showIpLogs();
        }

        return $this->showRecentLogs();
    }

    private function showIpLogs(): int
    {
        $ip = $this->option('ip');
        $days = (int) $this->option('days');

        $query = LogModel::fromIp($ip)
            ->where('created_at', '>=', now()->subDays($days));

        if ($this->option('status')) {
            $query->where('status_code', $this->option('status'));
        }

        if ($this->option('event')) {
            $query->withEvent($this->option('event'));
        }

        $logs = $query->orderByDesc('created_at')->get();

        if ($logs->isEmpty()) {
            $this->comment("No logs found for IP {$ip} in the last {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($logs->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Logs for {$ip} (last {$days} days)");
        $this->newLine();

        $requests = $logs->where('event', 'request');
        $events = $logs->where('event', '!=', 'request');

        if ($requests->isNotEmpty()) {
            $this->info("Suspicious Requests ({$requests->count()})");
            $this->newLine();

            $rows = $requests->map(fn ($log) => [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->method ?? '-',
                $log->path ?? '-',
                $log->status_code ?? '-',
                $log->score,
                mb_substr($log->user_agent ?? '-', 0, 40),
            ])->toArray();

            $this->table(['Time', 'Method', 'Path', 'Status', 'Score', 'User Agent'], $rows);
        }

        if ($events->isNotEmpty()) {
            $this->newLine();
            $this->info("Block Events ({$events->count()})");
            $this->newLine();

            $rows = $events->map(fn ($log) => [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->event,
                $log->reason,
                $log->score,
            ])->toArray();

            $this->table(['Time', 'Event', 'Reason', 'Score'], $rows);
        }

        $this->newLine();
        $this->line("Total: {$logs->count()} log(s)");

        return self::SUCCESS;
    }

    private function showRecentLogs(): int
    {
        $days = (int) $this->option('days');

        $query = LogModel::where('created_at', '>=', now()->subDays($days));

        if ($this->option('status')) {
            $query->where('status_code', $this->option('status'));
        }

        if ($this->option('event')) {
            $query->withEvent($this->option('event'));
        }

        $logs = $query->orderByDesc('created_at')->limit(50)->get();

        if ($logs->isEmpty()) {
            $this->comment("No logs found in the last {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($logs->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Recent logs (last {$days} days, showing max 50)");
        $this->newLine();

        $rows = $logs->map(fn ($log) => [
            $log->created_at->format('Y-m-d H:i:s'),
            $log->ip,
            $log->event,
            $log->path ?? '-',
            $log->status_code ?? '-',
            $log->score,
            mb_substr($log->reason, 0, 30),
        ])->toArray();

        $this->table(['Time', 'IP', 'Event', 'Path', 'Status', 'Score', 'Reason'], $rows);

        return self::SUCCESS;
    }

    private function pruneLogs(): int
    {
        $retentionDays = config('gupa.master.log_retention_days', 30);

        $deleted = LogModel::olderThan($retentionDays)->delete();

        $this->info("Pruned {$deleted} log(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
