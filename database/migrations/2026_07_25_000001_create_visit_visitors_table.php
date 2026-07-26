<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('visit_visitors', function (Blueprint $t) {
            $t->id();

            // types
            $t->string('device_type')->nullable();
            // browser | mobile app | library | feed reader | pim | media player — orthogonal to
            // is_bot, matomo/device-detector classifies plenty of that traffic as non-bot
            $t->string('client_type')->nullable()->index();

            // content + bool
            $t->string('token', 64)->unique();
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->text('first_landing_url')->nullable();
            $t->string('first_referrer_url', 2048)->nullable();
            $t->string('first_referrer_host')->nullable()->index();
            // first-touch, written once, never overwritten
            $t->string('utm_source')->nullable()->index();
            $t->string('utm_medium')->nullable();
            $t->string('utm_campaign')->nullable();
            $t->string('utm_term')->nullable();
            $t->string('utm_content')->nullable();
            $t->string('ref')->nullable()->index();
            // first-touch, written once, never overwritten — same semantics as utm_source/ref
            $t->string('search_term')->nullable()->index();
            // last-known, mutable, updated every new session
            $t->string('country_code', 2)->nullable()->index();
            $t->string('region')->nullable();
            $t->string('city')->nullable();
            $t->string('timezone')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->string('locale', 10)->nullable()->index();
            $t->string('browser_language', 10)->nullable();
            $t->string('platform')->nullable();
            $t->string('browser')->nullable();
            $t->boolean('is_bot')->default(false)->index();

            // json
            $t->json('extra_params')->nullable();
            // country_name, currency_code, region_code, zip_code, postal_code, metro_code,
            // area_code, driver — populated inconsistently across stevebauman/location drivers,
            // never a useful rollup dimension. Same core/extra split as device_meta.
            $t->json('geo_meta')->nullable();
            // device_family, device_model, platform_version, browser_version, browser_engine —
            // real detail matomo/device-detector exposes, but none of it is ever filtered/grouped
            // by (high cardinality, e.g. exact browser build numbers) — same core/extra split as
            // tracking_params, not worth a dedicated column each
            $t->json('device_meta')->nullable();

            $t->timestamps();

            // relation fields
            // mutable "current known identity" — updated on every Login, never reset on Logout by default
            // Plain string id (not nullableMorphs()'s default unsignedBigInteger) — same reasoning
            // as eventable_id on visit_events: the host's auth model may use a UUID/ULID key.
            $t->string('user_type')->nullable();
            $t->string('user_id')->nullable();
            $t->index(['user_type', 'user_id']);
            // default '' (not NULL) — aggregation queries compare tenant_id = '' for the
            // no-tenant case, and NULL never equals '' in SQL; see visit_stats_daily for the
            // same reasoning.
            $t->string('tenant_id')->default('')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_visitors');
    }
};
