<?php

namespace App\Http\Traits;

trait Observable
{
    public static function bootObservable(): void
    {
        $observer = class_basename(self::class).'Observer';

        self::observe("App\Http\Observers\\$observer");
    }
}
