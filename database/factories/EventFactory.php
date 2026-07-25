<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Database\Factories;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $isAction = fake()->boolean(15);

        return [
            // standalone defaults — the seed command overrides both to chain onto an
            // already-created Session/Visitor pair
            'session_id' => Session::factory(),
            'visitor_id' => Visitor::factory(),
            'type' => $isAction ? Event::TYPE_ACTION : Event::TYPE_PAGE_VIEW,
            'action' => $isAction ? fake()->randomElement(['lead.created', 'order.placed', 'newsletter.subscribed', 'demo.requested']) : null,
            'url' => 'https://example.test/' . fake()->randomElement(['', 'pricing', 'blog/post-1', 'features', 'contact', 'cart']),
            'is_bot' => false,
            'bot_name' => null,
            'bot_category' => null,
            'eventable_type' => null,
            'eventable_id' => null,
            'meta' => $isAction ? ['amount' => fake()->randomFloat(2, 10, 500)] : null,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => [
            'is_bot' => true,
            'bot_name' => fake()->randomElement(['Googlebot', 'Bingbot', 'AhrefsBot']),
            'bot_category' => 'Search bot',
            'type' => Event::TYPE_PAGE_VIEW,
            'action' => null,
        ]);
    }
}
