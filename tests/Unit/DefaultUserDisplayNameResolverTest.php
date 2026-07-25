<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\Resolvers\DefaultUserDisplayNameResolver;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;

class DefaultUserDisplayNameResolverTest extends TestCase
{
    private function resolver(): DefaultUserDisplayNameResolver
    {
        return new DefaultUserDisplayNameResolver();
    }

    public function test_resolves_the_name_attribute(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);

        $this->assertSame('Vas', $this->resolver()->resolve($user));
    }

    public function test_falls_back_to_email_when_name_is_empty(): void
    {
        $user = TestUser::create(['name' => null, 'email' => 'vas@example.test']);

        $this->assertSame('vas@example.test', $this->resolver()->resolve($user));
    }

    public function test_returns_null_when_neither_present(): void
    {
        $user = TestUser::create(['name' => null, 'email' => null]);

        $this->assertNull($this->resolver()->resolve($user));
    }
}
