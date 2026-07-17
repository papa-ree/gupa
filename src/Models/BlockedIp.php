<?php

namespace Bale\Gupa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class BlockedIp extends Model
{
    protected $table = 'gupa_blocked_ips';

    protected $fillable = [
        'ip',
        'reason',
        'is_permanent',
        'expires_at',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlockedIp $model) {
            if (is_null($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_permanent', false)
            ->where('expires_at', '<=', now());
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_permanent', true)
                ->orWhere('expires_at', '>', now())
                ->orWhereNull('expires_at');
        });
    }

    public function isExpired(): bool
    {
        return !$this->is_permanent
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
