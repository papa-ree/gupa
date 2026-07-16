<?php

use Bale\Gupa\Actions\NotifyAction;
use Bale\Gupa\Notifications\BlockNotification;
use Bale\Gupa\Notifications\Channels\EmailChannel;
use Bale\Gupa\Notifications\Channels\WebhookChannel;
use Illuminate\Support\Facades\Http;

it('creates notification from config', function () {
    config()->set('gupa.notifications.enabled', false);

    $action = NotifyAction::fromConfig();

    expect($action)->toBeInstanceOf(NotifyAction::class);
});

it('does nothing when notifications disabled', function () {
    config()->set('gupa.notifications.enabled', false);

    $action = NotifyAction::fromConfig();
    $action->block('10.0.0.1', 'test', 100);

    // No exception = pass
    expect(true)->toBeTrue();
});

it('builds block notification array', function () {
    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'Score exceeded',
        score: 120,
        permanent: false,
        timestamp: '2026-01-01T00:00:00+00:00',
    );

    $array = $notification->toArray();

    expect($array['ip'])->toBe('10.0.0.1');
    expect($array['reason'])->toBe('Score exceeded');
    expect($array['score'])->toBe(120);
    expect($array['permanent'])->toBeFalse();
    expect($array['event'])->toBe('block');
    expect($array['app'])->toBeString();
});

it('builds email subject for block', function () {
    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: false,
        timestamp: '2026-01-01T00:00:00+00:00',
    );

    expect($notification->toEmailSubject())->toContain('10.0.0.1');
    expect($notification->toEmailSubject())->toContain('Blocked');
});

it('builds email subject for permanent block', function () {
    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: true,
        timestamp: '2026-01-01T00:00:00+00:00',
    );

    expect($notification->toEmailSubject())->toContain('Permanently Blocked');
});

it('builds email subject for unblock', function () {
    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'manual unblock',
        score: 0,
        permanent: false,
        timestamp: '2026-01-01T00:00:00+00:00',
        event: 'unblock',
    );

    expect($notification->toEmailSubject())->toContain('Unblocked');
});

it('builds email body', function () {
    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'Score exceeded',
        score: 120,
        permanent: false,
        timestamp: '2026-01-01T00:00:00+00:00',
    );

    $body = $notification->toEmailBody();

    expect($body)->toContain('10.0.0.1');
    expect($body)->toContain('Score exceeded');
    expect($body)->toContain('120');
});

it('sends webhook notification', function () {
    config()->set('gupa.notifications.enabled', true);
    config()->set('gupa.notifications.channels.webhook.enabled', true);
    config()->set('gupa.notifications.channels.webhook.url', 'https://example.com/hook');
    config()->set('gupa.notifications.channels.webhook.secret', 'my-secret');

    Http::fake();

    $channel = new WebhookChannel(config('gupa.notifications.channels.webhook'));

    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: false,
        timestamp: now()->toIso8601String(),
    );

    $result = $channel->send($notification);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/hook'
            && $request->hasHeader('X-Gupa-Secret')
            && $request->hasHeader('Content-Type');
    });
});

it('returns false when webhook url is missing', function () {
    $channel = new WebhookChannel(['url' => '', 'enabled' => true]);

    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: false,
        timestamp: now()->toIso8601String(),
    );

    $result = $channel->send($notification);

    expect($result)->toBeFalse();
});

it('sends email notification', function () {
    config()->set('gupa.notifications.enabled', true);
    config()->set('gupa.notifications.channels.email.enabled', true);
    config()->set('gupa.notifications.channels.email.to', 'admin@example.com');
    config()->set('gupa.notifications.channels.email.from', 'gupa@example.com');

    $channel = new EmailChannel(config('gupa.notifications.channels.email'));

    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: false,
        timestamp: now()->toIso8601String(),
    );

    $result = $channel->send($notification);

    expect($result)->toBeTrue();
});

it('returns false when email to is missing', function () {
    $channel = new EmailChannel(['to' => '', 'enabled' => true]);

    $notification = new BlockNotification(
        ip: '10.0.0.1',
        reason: 'test',
        score: 100,
        permanent: false,
        timestamp: now()->toIso8601String(),
    );

    $result = $channel->send($notification);

    expect($result)->toBeFalse();
});

it('dispatches to configured channels', function () {
    config()->set('gupa.notifications.enabled', true);
    config()->set('gupa.notifications.channels.webhook.enabled', true);
    config()->set('gupa.notifications.channels.webhook.url', 'https://example.com/hook');
    config()->set('gupa.notifications.channels.webhook.secret', '');
    config()->set('gupa.notifications.channels.email.enabled', false);

    Http::fake();

    $action = NotifyAction::fromConfig();
    $action->block('10.0.0.1', 'test', 100);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/hook';
    });
});
