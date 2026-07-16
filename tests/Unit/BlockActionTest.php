<?php

use Bale\Gupa\Actions\BlockAction;
use Illuminate\Support\Facades\Cache;

it('blocks an IP temporarily', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->execute('10.0.0.1');

    expect($blockAction->isBlocked('10.0.0.1'))->toBeTrue();
});

it('blocks an IP permanently', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->execute('10.0.0.1', permanent: true);

    expect($blockAction->isBlocked('10.0.0.1'))->toBeTrue();
    expect(Cache::has('gupa:permanent:10.0.0.1'))->toBeTrue();
});

it('unblocks an IP', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->execute('10.0.0.1');
    expect($blockAction->isBlocked('10.0.0.1'))->toBeTrue();

    $blockAction->unblock('10.0.0.1');
    expect($blockAction->isBlocked('10.0.0.1'))->toBeFalse();
});

it('sets and detects pending block', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->setPendingBlock('10.0.0.1');

    expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeTrue();
    expect($blockAction->isBlocked('10.0.0.1'))->toBeFalse();
});

it('applies pending block', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->setPendingBlock('10.0.0.1');
    expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeTrue();

    $blockAction->applyPendingBlock('10.0.0.1');

    expect($blockAction->hasPendingBlock('10.0.0.1'))->toBeFalse();
    expect($blockAction->isBlocked('10.0.0.1'))->toBeTrue();
});

it('tracks block count', function () {
    $blockAction = app(BlockAction::class);

    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(0);

    $blockAction->execute('10.0.0.1');
    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(1);

    $blockAction->unblock('10.0.0.1');
    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(0);

    $blockAction->execute('10.0.0.1');
    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(1);
});

it('resets block count on unblock', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->execute('10.0.0.1');
    $blockAction->execute('10.0.0.1');
    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(2);

    $blockAction->unblock('10.0.0.1');
    expect($blockAction->getBlockCount('10.0.0.1'))->toBe(0);
});

it('returns false for non-blocked IP', function () {
    $blockAction = app(BlockAction::class);

    expect($blockAction->isBlocked('10.0.0.99'))->toBeFalse();
});

it('unblocks all related cache keys', function () {
    $blockAction = app(BlockAction::class);

    $blockAction->execute('10.0.0.1');
    $blockAction->setPendingBlock('10.0.0.1');

    $blockAction->unblock('10.0.0.1');

    expect(Cache::has('gupa:blocked:10.0.0.1'))->toBeFalse();
    expect(Cache::has('gupa:pending_block:10.0.0.1'))->toBeFalse();
    expect(Cache::has('gupa:permanent:10.0.0.1'))->toBeFalse();
    expect(Cache::has('gupa:block_count:10.0.0.1'))->toBeFalse();
});
