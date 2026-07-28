<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Support\TokenResolver;
use Fomvasss\Visits\Tests\Fixtures\TestUser;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenResolverTest extends TestCase
{
    private TokenResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TokenResolver();
    }

    /**
     * Valid-format UUID, distinguishable per test by its repeated hex digit — real UUIDs would
     * work just as well, but a fixed, readable value makes assertions easier to follow.
     */
    private function uuidOf(string $hexDigit): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            str_repeat($hexDigit, 8),
            str_repeat($hexDigit, 4),
            str_repeat($hexDigit, 4),
            str_repeat($hexDigit, 4),
            str_repeat($hexDigit, 12),
        );
    }

    public function test_generate_returns_a_uuid(): void
    {
        $token = $this->resolver->generate();

        $this->assertTrue(Str::isUuid($token));
        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_rejects_malformed_uuids(): void
    {
        $this->assertFalse($this->resolver->isValidFormat(str_repeat('a', 36)), 'no dashes');
        $this->assertFalse($this->resolver->isValidFormat($this->uuidOf('a') . '0'), 'too long');
        $this->assertFalse($this->resolver->isValidFormat(substr($this->uuidOf('a'), 0, -1)), 'too short');
        $this->assertFalse($this->resolver->isValidFormat('gggggggg-gggg-gggg-gggg-gggggggggggg'), 'non-hex characters');
        $this->assertTrue($this->resolver->isValidFormat($this->uuidOf('a')));
    }

    public function test_client_header_token_wins_over_cookie(): void
    {
        $headerToken = $this->uuidOf('1');
        $cookieToken = $this->uuidOf('2');

        $request = Request::create('/', 'GET');
        $request->headers->set(TokenResolver::HEADER, $headerToken);
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($headerToken, $this->resolver->resolve($request));
    }

    public function test_client_body_token_wins_over_cookie(): void
    {
        $bodyToken = $this->uuidOf('3');
        $cookieToken = $this->uuidOf('2');

        $request = Request::create('/', 'POST', [TokenResolver::INPUT_KEY => $bodyToken]);
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($bodyToken, $this->resolver->resolve($request));
    }

    public function test_falls_back_to_cookie_when_no_client_token(): void
    {
        $cookieToken = $this->uuidOf('2');

        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($cookieToken, $this->resolver->resolve($request));
    }

    public function test_generates_fresh_token_when_nothing_present(): void
    {
        $request = Request::create('/', 'GET');

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_malformed_client_token_is_ignored_in_favor_of_cookie(): void
    {
        $cookieToken = $this->uuidOf('2');

        $request = Request::create('/', 'GET');
        $request->headers->set(TokenResolver::HEADER, 'not_a_valid_token!!');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($cookieToken, $this->resolver->resolve($request));
    }

    public function test_malformed_cookie_token_falls_back_to_generated(): void
    {
        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), 'bad token with spaces');

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
        $this->assertNotSame('bad token with spaces', $token);
    }

    public function test_fallback_is_used_when_no_client_token_or_cookie(): void
    {
        $fallbackToken = $this->uuidOf('4');
        $request = Request::create('/', 'POST');

        $this->assertSame($fallbackToken, $this->resolver->resolve($request, fn () => $fallbackToken));
    }

    public function test_fallback_is_never_consulted_when_a_cookie_is_present(): void
    {
        $cookieToken = $this->uuidOf('2');
        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $token = $this->resolver->resolve($request, function () {
            $this->fail('fallback should not be consulted when the cookie already resolves a token');
        });

        $this->assertSame($cookieToken, $token);
    }

    public function test_malformed_fallback_falls_back_to_generated(): void
    {
        $request = Request::create('/', 'POST');

        $token = $this->resolver->resolve($request, fn () => 'not-a-valid-token!!');

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_null_fallback_result_generates_a_fresh_token(): void
    {
        $request = Request::create('/', 'POST');

        $token = $this->resolver->resolve($request, fn () => null);

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_inherits_from_authenticated_users_visitor_when_no_other_signal(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
        $visitor = Visitor::factory()->create([
            'user_type' => TestUser::class, 'user_id' => $user->id, 'last_seen_at' => now(),
        ]);

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $this->assertSame($visitor->token, $this->resolver->resolve($request));
    }

    public function test_auth_inherit_prefers_the_most_recently_active_visitor(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
        Visitor::factory()->create([
            'user_type' => TestUser::class, 'user_id' => $user->id, 'last_seen_at' => now()->subDays(3),
        ]);
        $recent = Visitor::factory()->create([
            'user_type' => TestUser::class, 'user_id' => $user->id, 'last_seen_at' => now(),
        ]);

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $this->assertSame($recent->token, $this->resolver->resolve($request));
    }

    public function test_auth_inherit_never_consulted_when_a_cookie_is_present(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
        Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $cookieToken = $this->uuidOf('2');
        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);
        $request->setUserResolver(fn () => $user);

        $this->assertSame($cookieToken, $this->resolver->resolve($request));
    }

    public function test_explicit_fallback_wins_over_auth_inherit(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);
        Visitor::factory()->create(['user_type' => TestUser::class, 'user_id' => $user->id]);

        $fallbackToken = $this->uuidOf('4');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $this->assertSame($fallbackToken, $this->resolver->resolve($request, fn () => $fallbackToken));
    }

    public function test_generates_fresh_token_when_authenticated_user_has_no_visitor(): void
    {
        $user = TestUser::create(['name' => 'Vas', 'email' => 'vas@example.test']);

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_auth_inherit_skipped_when_user_model_has_no_visitor_profiles(): void
    {
        $plainUser = new class {
            public $id = 1;
        };

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $plainUser);

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_format_regex_is_configurable(): void
    {
        $legacyToken = str_repeat('a', 40);

        $this->assertFalse($this->resolver->isValidFormat($legacyToken));

        config(['visits.visitor_id.format_regex' => '/^[a-zA-Z0-9]{20,64}$/']);

        $this->assertTrue($this->resolver->isValidFormat($legacyToken));
        $this->assertFalse($this->resolver->isValidFormat($this->uuidOf('a') . '-extra'));
    }
}
