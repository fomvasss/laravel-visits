<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Facades;

use Fomvasss\Visits\VisitsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void track(string $action, ?\Illuminate\Database\Eloquent\Model $eventable = null, ?array $meta = null)
 * @method static array whoami(?\Illuminate\Http\Request $request = null, ?string $ip = null)
 */
class Visits extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VisitsManager::class;
    }
}
