<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('visit_events', function (Blueprint $t) {
            // url's path component only (no query string), computed once at write time —
            // grouping the Top Pages dashboard panel by this instead of raw url avoids
            // fragmenting a single page into one row per filter/sort query-param combination,
            // without needing a DB-portable "strip query string" SQL expression on every read.
            $t->string('path')->nullable()->index()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('visit_events', function (Blueprint $t) {
            $t->dropColumn('path');
        });
    }
};
