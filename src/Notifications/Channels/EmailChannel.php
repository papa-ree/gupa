<?php

namespace Bale\Gupa\Notifications\Channels;

use Bale\Gupa\Notifications\BlockNotification;
use Illuminate\Support\Facades\Mail;

class EmailChannel
{
    public function __construct(
        private array $config,
    ) {}

    public function send(BlockNotification $notification): bool
    {
        $to = $this->config['to'] ?? null;

        if (!$to) {
            return false;
        }

        try {
            $subject = $notification->toEmailSubject();
            $body = $notification->toEmailBody();

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject)
                    ->from($this->config['from'] ?? config('mail.from.address'));
            });

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
