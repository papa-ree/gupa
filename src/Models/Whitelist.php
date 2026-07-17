<?php

namespace Bale\Gupa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Whitelist extends Model
{
    protected $table = 'gupa_whitelists';

    public $timestamps = false;

    protected $fillable = ['ip'];

    protected static function booted(): void
    {
        static::creating(function (Whitelist $model) {
            if (is_null($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
