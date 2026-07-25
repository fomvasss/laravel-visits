<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Fixtures;

use Fomvasss\Visits\Contracts\ConsentResolverInterface;
use Illuminate\Http\Request;

class AlwaysConsentResolver implements ConsentResolverInterface
{
    public function hasConsent(Request $request): bool
    {
        return (bool) $request->cookie('consent');
    }
}
