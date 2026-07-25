<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('visit_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visitor_id')->constrained('visit_visitors')->cascadeOnDelete();

            // immutable snapshot — who was logged in during this specific session, set once at Login
            $t->nullableMorphs('user');

            $t->timestamp('started_at')->index();
            $t->timestamp('last_activity_at')->index();
            $t->timestamp('ended_at')->nullable();

            $t->text('landing_url')->nullable();
            $t->text('exit_url')->nullable();

            $t->string('referrer_url', 2048)->nullable();
            $t->string('referrer_host')->nullable()->index();

            // last-touch: overwritten if query has new values, otherwise inherited from visitor
            $t->string('utm_source')->nullable()->index();
            $t->string('utm_medium')->nullable();
            $t->string('utm_campaign')->nullable();
            $t->string('utm_term')->nullable();
            $t->string('utm_content')->nullable();
            $t->string('ref')->nullable();
            $t->json('extra_params')->nullable();

            $t->ipAddress('ip')->nullable()->index();

            // immutable snapshot at the time of this session
            $t->string('country_code', 2)->nullable()->index();
            $t->string('region')->nullable();
            $t->string('city')->nullable();
            $t->string('timezone')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->json('geo_meta')->nullable();

            $t->string('locale', 10)->nullable();
            $t->string('browser_language', 10)->nullable();

            $t->string('device_type')->nullable()->index();
            $t->string('platform')->nullable();
            $t->string('browser')->nullable();
            $t->string('client_type')->nullable()->index();
            $t->json('device_meta')->nullable();
            $t->text('user_agent')->nullable();

            $t->unsignedInteger('page_views_count')->default(0);
            $t->unsignedInteger('duration_seconds')->nullable();

            $t->boolean('is_bot')->default(false)->index();

            $t->timestamps();

            $t->index(['visitor_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_sessions');
    }
};
