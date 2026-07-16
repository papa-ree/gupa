<?php

namespace Bale\Gupa\Actions;

use Bale\Gupa\Notifications\Channels\EmailChannel;
use Bale\Gupa\Notifications\Channels\WebhookChannel;
use Bale\Gupa\Notifications\BlockNotification;

class NotifyAction
{
    private bool $enabled;
    private array $channels;

    public function __construct()
    {
        $this->enabled = (bool) config('gupa.notifications.enabled', false);
        $this->channels = config('gupa.notifications.channels', []);
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    public function block(string $ip, string $reason, int $score, bool $permanent = false): void
    {
        if (!$this->enabled) {
            return;
        }

        $notification = new BlockNotification(
            ip: $ip,
            reason: $reason,
            score: $score,
            permanent: $permanent,
            timestamp: now()->toIso8601String(),
        );

        $this->dispatch($notification);
    }

    public function unblock(string $ip, string $reason): void
    {
        if (!$this->enabled) {
            return;
        }

        $notification = new BlockNotification(
            ip: $ip,
            reason: $reason,
            score: 0,
            permanent: false,
            timestamp: now()->toIso8601String(),
            event: 'unblock',
        );

        $this->dispatch($notification);
    }

    private function dispatch(BlockNotification $notification): void
    {
        foreach ($this->channels as $channelName => $channelConfig) {
            if (!($channelConfig['enabled'] ?? false)) {
                continue;
            }

            $this->sendToChannel($channelName, $channelConfig, $notification);
        }
    }

    private function sendToChannel(string $name, array $config, BlockNotification $notification): void
    {
        $channel = match ($name) {
            'webhook' => new WebhookChannel($config),
            'email' => new EmailChannel($config),
            default => null,
        };

        if ($channel) {
            $channel->send($notification);
        }
    }
}
