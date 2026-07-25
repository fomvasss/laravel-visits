<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Support\ModelResolver;
use Fomvasss\Visits\Tests\Fixtures\OverriddenVisitor;
use Fomvasss\Visits\Tests\TestCase;

class ModelResolverTest extends TestCase
{
    public function test_resolves_default_classes_when_no_override_configured(): void
    {
        $this->assertSame(Visitor::class, ModelResolver::visitor());
        $this->assertSame(Session::class, ModelResolver::session());
        $this->assertSame(Event::class, ModelResolver::event());
        $this->assertSame(StatDaily::class, ModelResolver::statDaily());
    }

    public function test_resolves_overridden_visitor_class(): void
    {
        config(['visits.models.visitor' => OverriddenVisitor::class]);

        $this->assertSame(OverriddenVisitor::class, ModelResolver::visitor());
    }

    public function test_overridden_visitor_is_returned_by_session_relation(): void
    {
        config(['visits.models.visitor' => OverriddenVisitor::class]);

        // created via the plain Visitor factory — the relation below is what's under test,
        // not object identity from a factory (VisitorFactory hardcodes $model = Visitor::class,
        // so OverriddenVisitor::factory() would still hydrate plain Visitor instances)
        $visitor = Visitor::factory()->create();
        $session = Session::factory()->create(['visitor_id' => $visitor->id]);

        $this->assertInstanceOf(OverriddenVisitor::class, $session->visitor);
    }
}
