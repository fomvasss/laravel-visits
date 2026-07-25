<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\Fixtures\FullNameResolver;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;

class HasUserDisplayNameTest extends TestCase
{
    public function test_returns_null_when_no_user_attached(): void
    {
        $visitor = Visitor::factory()->create(['user_type' => null, 'user_id' => null]);

        $this->assertNull($visitor->userDisplayName());
    }

    public function test_returns_null_when_user_relation_is_missing(): void
    {
        $visitor = Visitor::factory()->create([
            'user_type' => TestUser::class,
            'user_id' => 999999,
        ]);

        $this->assertNull($visitor->userDisplayName());
    }

    public function test_uses_the_default_resolver_by_default(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
        $visitor = Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->assertSame('Vas', $visitor->userDisplayName());
    }

    public function test_default_resolver_falls_back_to_email_when_name_is_empty(): void
    {
        $user = TestUser::create(['name' => null, 'email' => 'vas@example.test']);
        $visitor = Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->assertSame('vas@example.test', $visitor->userDisplayName());
    }

    public function test_default_resolver_returns_null_when_neither_name_nor_email_present(): void
    {
        $user = TestUser::create(['name' => null, 'email' => null]);
        $visitor = Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->assertNull($visitor->userDisplayName());
    }

    public function test_respects_a_custom_resolver_class(): void
    {
        config(['visits.user_display_resolver' => FullNameResolver::class]);

        $user = TestUser::create(['first_name' => 'Vasyl', 'last_name' => 'Fomin', 'name' => 'Vas', 'email' => 'vas@example.test']);
        $visitor = Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->assertSame('Vasyl Fomin <vas@example.test>', $visitor->userDisplayName());
    }
}
