<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Tests\TestCase;

class SessionTest extends TestCase
{
    public function test_is_open_when_ended_at_is_null(): void
    {
        $session = Session::factory()->create(['ended_at' => null]);

        $this->assertTrue($session->isOpen());
    }

    public function test_is_not_open_once_ended_at_is_set(): void
    {
        $session = Session::factory()->create(['ended_at' => now()]);

        $this->assertFalse($session->isOpen());
    }
}
