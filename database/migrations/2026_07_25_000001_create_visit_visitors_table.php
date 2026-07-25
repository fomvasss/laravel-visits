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
            $t->string('token', 64)->unique();

            // mutable "current known identity" — updated on every Login, never reset on Logout by default
            $t->nullableMorphs('user');

            $t->string('tenant_id')->nullable()->index();

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
            $t->json('extra_params')->nullable();

            // last-known, mutable, updated every new session
            $t->string('country_code', 2)->nullable()->index();
            $t->string('region')->nullable();
            $t->string('city')->nullable();
            $t->string('timezone')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();

            $t->string('locale', 10)->nullable()->index();
            $t->string('browser_language', 10)->nullable();

            $t->string('device_type')->nullable();
            $t->string('device_family')->nullable();
            $t->string('device_model')->nullable();
            $t->string('platform')->nullable();
            $t->string('browser')->nullable();

            $t->boolean('is_bot')->default(false)->index();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_visitors');
    }
};
