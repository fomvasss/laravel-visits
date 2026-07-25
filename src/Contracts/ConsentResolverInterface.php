<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Contracts;

use Illuminate\Http\Request;

interface ConsentResolverInterface
{
    public function hasConsent(Request $request): bool;
}
