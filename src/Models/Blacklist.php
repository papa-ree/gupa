<?php

namespace Bale\Gupa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blacklist extends Model
{
    protected $table = 'gupa_blacklists';

    public $timestamps = false;

    protected $fillable = ['ip'];

    protected static function booted(): void
    {
        static::creating(function (Blacklist $model) {
            if (is_null($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
