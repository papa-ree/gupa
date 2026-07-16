<?php

namespace Bale\Gupa\Notifications;

class BlockNotification
{
    public function __construct(
        public readonly string $ip,
        public readonly string $reason,
        public readonly int $score,
        public readonly bool $permanent,
        public readonly string $timestamp,
        public readonly string $event = 'block',
    ) {}

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'ip' => $this->ip,
            'reason' => $this->reason,
            'score' => $this->score,
            'permanent' => $this->permanent,
            'timestamp' => $this->timestamp,
            'app' => config('app.name', 'unknown'),
        ];
    }

    public function toEmailSubject(): string
    {
        $action = $this->event === 'unblock' ? 'Unblocked' : ($this->permanent ? 'Permanently Blocked' : 'Blocked');

        return "Gupa: IP {$this->ip} {$action}";
    }

    public function toEmailBody(): string
    {
        $action = $this->event === 'unblock' ? 'unblocked' : ($this->permanent ? 'permanently blocked' : 'blocked');

        return implode("\n", [
            "IP Address: {$this->ip}",
            "Event: {$this->event}",
            "Action: {$action}",
            "Reason: {$this->reason}",
            "Score: {$this->score}",
            "Timestamp: {$this->timestamp}",
            "Application: " . config('app.name', 'unknown'),
        ]);
    }
}
