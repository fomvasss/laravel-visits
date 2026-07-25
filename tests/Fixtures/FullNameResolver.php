<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Fixtures;

use Fomvasss\Visits\Contracts\UserDisplayNameResolverInterface;

class FullNameResolver implements UserDisplayNameResolverInterface
{
    public function resolve(mixed $user): ?string
    {
        return $user->fullName() . ' <' . $user->email . '>';
    }
}
