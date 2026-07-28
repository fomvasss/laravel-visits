<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Feature;

use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class IdentityListenersTest extends TestCase
{
    // must satisfy TokenResolver::isValidFormat() — a UUID — or TokenResolver::resolve()
    // silently discards it and generates a fresh random one instead, which then never matches
    // the visitor row seeded with this exact token
    private const TOKEN = '11111111-1111-1111-1111-111111111111';

    private function bindRequestWithVisitorCookie(string $token = self::TOKEN): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), $token);

        $this->app->instance(Request::class, $request);
    }

    private function user(): TestUser
    {
        return TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
    }

    public function test_login_sets_visitor_identity_and_open_session(): void
    {
        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => null, 'user_id' => null]);
        $session = Session::factory()->create([
            'visitor_id' => $visitor->id,
            'user_id' => null,
            'user_type' => null,
            'ended_at' => null,
            'last_activity_at' => now(),
        ]);

        $this->bindRequestWithVisitorCookie();
        $user = $this->user();

        event(new Login('web', $user, false));

        $visitor->refresh();
        $session->refresh();

        $this->assertSame(TestUser::class, $visitor->user_type);
        $this->assertSame((string) $user->id, $visitor->user_id);
        $this->assertSame(TestUser::class, $session->user_type);
        $this->assertSame((string) $user->id, $session->user_id);
    }

    /**
     * A host that registers Relation::morphMap() (a common Laravel convention) makes
     * getMorphClass() return the alias, not the FQCN — HasVisits::visitorProfiles() (morphMany)
     * filters by that same alias when reading, so writing the raw FQCN here would silently
     * never match, even though the row is otherwise linked correctly.
     */
    public function test_login_uses_the_morph_map_alias_when_registered(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['app_user' => TestUser::class]);

        try {
            $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => null, 'user_id' => null]);

            $this->bindRequestWithVisitorCookie();
            $user = $this->user();

            event(new Login('web', $user, false));

            $this->assertSame('app_user', $visitor->refresh()->user_type);
        } finally {
            \Illuminate\Database\Eloquent\Relations\Relation::morphMap([], false);
        }
    }

    public function test_login_does_not_overwrite_a_session_already_attributed(): void
    {
        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => null, 'user_id' => null]);
        $previousUser = $this->user();
        $session = Session::factory()->create([
            'visitor_id' => $visitor->id,
            'user_id' => $previousUser->id,
            'user_type' => TestUser::class,
            'ended_at' => null,
            'last_activity_at' => now(),
        ]);

        $this->bindRequestWithVisitorCookie();
        $newUser = $this->user();

        event(new Login('web', $newUser, false));

        $visitor->refresh();
        $session->refresh();

        $this->assertSame((string) $newUser->id, $visitor->user_id, 'visitor identity is mutable, always updated');
        $this->assertSame((string) $previousUser->id, $session->user_id, 'session identity is an immutable snapshot');
    }

    public function test_login_ignores_sessions_past_the_timeout(): void
    {
        config(['visits.session_timeout_minutes' => 30]);

        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => null, 'user_id' => null]);
        $session = Session::factory()->create([
            'visitor_id' => $visitor->id,
            'user_id' => null,
            'user_type' => null,
            'ended_at' => null,
            'last_activity_at' => now()->subHours(2),
        ]);

        $this->bindRequestWithVisitorCookie();
        event(new Login('web', $this->user(), false));

        $visitor->refresh();
        $session->refresh();

        $this->assertNotNull($visitor->user_id);
        $this->assertNull($session->user_id, 'a stale session outside the timeout window is not touched');
    }

    public function test_login_does_nothing_when_no_visitor_matches_the_token(): void
    {
        $this->bindRequestWithVisitorCookie('99999999-9999-9999-9999-999999999999');

        event(new Login('web', $this->user(), false));

        $this->assertSame(0, Visitor::withBots()->count());
    }

    public function test_login_does_nothing_when_package_disabled(): void
    {
        config(['visits.enabled' => false]);

        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => null, 'user_id' => null]);

        $this->bindRequestWithVisitorCookie();
        event(new Login('web', $this->user(), false));

        $this->assertNull($visitor->refresh()->user_id);
    }

    public function test_logout_resets_identity_when_configured(): void
    {
        config(['visits.reset_identity_on_logout' => true]);

        $user = $this->user();
        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->bindRequestWithVisitorCookie();
        event(new Logout('web', $user));

        $visitor->refresh();
        $this->assertNull($visitor->user_type);
        $this->assertNull($visitor->user_id);
    }

    public function test_logout_keeps_identity_by_default(): void
    {
        $user = $this->user();
        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => TestUser::class, 'user_id' => $user->id]);

        $this->bindRequestWithVisitorCookie();
        event(new Logout('web', $user));

        $visitor->refresh();
        $this->assertSame(TestUser::class, $visitor->user_type);
        $this->assertSame((string) $user->id, $visitor->user_id);
    }

    public function test_logout_never_touches_session_identity_snapshot(): void
    {
        config(['visits.reset_identity_on_logout' => true]);

        $user = $this->user();
        $visitor = Visitor::factory()->create(['token' => self::TOKEN, 'user_type' => TestUser::class, 'user_id' => $user->id]);
        $session = Session::factory()->create([
            'visitor_id' => $visitor->id,
            'user_type' => TestUser::class,
            'user_id' => $user->id,
        ]);

        $this->bindRequestWithVisitorCookie();
        event(new Logout('web', $user));

        $this->assertSame((string) $user->id, $session->refresh()->user_id);
    }
}
