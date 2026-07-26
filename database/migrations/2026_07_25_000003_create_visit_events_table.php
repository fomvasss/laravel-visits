<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $t) {
            $t->id();

            // types
            $t->string('type', 20)->index(); // page_view | action

            // content + bool
            $t->string('name')->nullable()->index(); // free string, e.g. 'order.placed', 'lead.created'
            // the Laravel-named route for this request, distinct from the raw url column below —
            // lets a host filter/group by route identity instead of string-matching URLs that
            // vary by query string/trailing slash. Only populated for requests that actually
            // went through Laravel's router with the visited page as the current request (the
            // automatic TrackVisit middleware, and Visits::track() called from within a request);
            // null for POST /visits/collect, whose own matched route is never the page the
            // client is reporting.
            $t->string('route_name')->nullable()->index();
            $t->text('url')->nullable();
            // url's path component only (no query string), computed once at write time —
            // grouping the Top Pages dashboard panel by this instead of raw url avoids
            // fragmenting a single page into one row per filter/sort query-param combination,
            // without needing a DB-portable "strip query string" SQL expression on every read.
            $t->string('path')->nullable()->index();
            $t->boolean('is_bot')->default(false)->index();
            $t->string('bot_name')->nullable();
            $t->string('bot_category')->nullable();

            // json
            $t->json('meta')->nullable();

            $t->timestamp('created_at')->index();

            // relation fields
            $t->foreignId('session_id')->constrained('visit_sessions')->cascadeOnDelete();
            // denormalized for convenient querying without joining through session
            $t->foreignId('visitor_id')->constrained('visit_visitors')->cascadeOnDelete();
            // Plain string id (not nullableMorphs()'s default unsignedBigInteger) — the package
            // doesn't control the host's business model primary keys, and can't assume every
            // eventable model uses a bigint auto-increment id. A string holds both a bigint's
            // string form and a real UUID/ULID without truncation, same reasoning as tenant_id
            // being a generic string elsewhere.
            $t->string('eventable_type')->nullable();
            $t->string('eventable_id')->nullable();

            // indexes
            $t->index(['eventable_type', 'eventable_id']);
            $t->index(['session_id', 'created_at']);
            $t->index(['name', 'created_at']);
            // Dashboard's Top Pages panel filters type=page_view over a date range then groups
            // by path — the separate single-column type/created_at indexes above only serve one
            // side of that filter each.
            $t->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
