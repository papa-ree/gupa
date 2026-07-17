<?php

namespace Bale\Gupa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Log extends Model
{
    protected $table = 'gupa_logs';

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'event',
        'reason',
        'score',
        'path',
        'method',
        'user_agent',
        'status_code',
        'metadata',
    ];

    protected $casts = [
        'score' => 'integer',
        'status_code' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Log $log) {
            if (is_null($log->id)) {
                $log->id = (string) Str::uuid();
            }
            if (is_null($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    public function scopeFromIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    public function scopeWithEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeRequests($query)
    {
        return $query->where('event', 'request');
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}
