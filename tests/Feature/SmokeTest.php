<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_visitor_can_be_created_via_factory(): void
    {
        $visitor = Visitor::factory()->create();

        $this->assertDatabaseHas('visit_visitors', ['id' => $visitor->id]);
    }
}
