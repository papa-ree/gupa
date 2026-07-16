<?php

namespace Bale\Gupa\Commands;

use Bale\Gupa\Scorer\ScoreCalculator;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'gupa:stats {--json : Output as JSON}';

    protected $description = 'Show Gupa guardian status and configuration';

    public function handle(ScoreCalculator $scoreCalculator): int
    {
        $data = [
            'version' => '0.1.0',
            'enabled' => config('gupa.master.enabled', true),
            'threshold' => config('gupa.master.threshold', 100),
            'score_decay_window' => config('gupa.master.score_decay_window', 300),
            'block_duration' => config('gupa.master.block_duration', 3600),
            'log_enabled' => config('gupa.master.log_enabled', true),
            'whitelist_enabled' => config('gupa.whitelist.enabled', true),
            'whitelist_ips' => config('gupa.whitelist.ips', []),
            'blacklist_enabled' => config('gupa.blacklist.enabled', false),
            'blacklist_ips' => config('gupa.blacklist.ips', []),
            'active_detectors' => $scoreCalculator->getActiveDetectors(),
            'all_detectors' => $scoreCalculator->getAllDetectors(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Gupa Guardian Status');
        $this->newLine();

        $this->table(['Property', 'Value'], [
            ['Version', $data['version']],
            ['Enabled', $data['enabled'] ? 'Yes' : 'No'],
            ['Threshold', $data['threshold']],
            ['Score Decay Window', "{$data['score_decay_window']}s"],
            ['Block Duration', "{$data['block_duration']}s"],
            ['Log Enabled', $data['log_enabled'] ? 'Yes' : 'No'],
            ['Whitelist Enabled', $data['whitelist_enabled'] ? 'Yes' : 'No'],
            ['Whitelist IPs', implode(', ', $data['whitelist_ips'])],
            ['Blacklist Enabled', $data['blacklist_enabled'] ? 'Yes' : 'No'],
            ['Blacklist IPs', implode(', ', $data['blacklist_ips']) ?: '(none)'],
        ]);

        if (!empty($data['all_detectors'])) {
            $this->newLine();
            $this->info('Detectors');
            $this->newLine();

            $rows = array_map(fn ($d) => [
                $d['name'],
                $d['enabled'] ? 'ON' : 'OFF',
            ], $data['all_detectors']);

            $this->table(['Detector', 'Status'], $rows);
        } else {
            $this->newLine();
            $this->comment('No detectors registered yet. Detectors will be added in Phase 2+.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
