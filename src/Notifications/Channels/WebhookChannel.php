<?php

namespace Bale\Gupa\Notifications\Channels;

use Bale\Gupa\Notifications\BlockNotification;
use Illuminate\Support\Facades\Http;

class WebhookChannel
{
    public function __construct(
        private array $config,
    ) {}

    public function send(BlockNotification $notification): bool
    {
        $url = $this->config['url'] ?? null;

        if (!$url) {
            return false;
        }

        $payload = $notification->toArray();
        $payload['channel'] = 'webhook';

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders($this->buildHeaders())
                ->post($url, $payload);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $secret = $this->config['secret'] ?? null;

        if ($secret) {
            $headers['X-Gupa-Secret'] = $secret;
        }

        return $headers;
    }
}
