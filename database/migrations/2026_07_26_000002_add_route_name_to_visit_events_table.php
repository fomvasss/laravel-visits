<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('visit_events', function (Blueprint $t) {
            // the Laravel-named route for this request, distinct from the raw url column —
            // lets a host filter/group by route identity instead of string-matching URLs that
            // vary by query string/trailing slash. Only populated for requests that actually
            // went through Laravel's router with the visited page as the current request (the
            // automatic TrackVisit middleware, and Visits::track() called from within a request);
            // null for POST /visits/collect, whose own matched route is never the page the
            // client is reporting.
            $t->string('route_name')->nullable()->index()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('visit_events', function (Blueprint $t) {
            $t->dropColumn('route_name');
        });
    }
};
