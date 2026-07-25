<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('visit_visitors', function (Blueprint $t) {
            // first-touch, written once, never overwritten — same semantics as utm_source/ref
            $t->string('search_term')->nullable()->index()->after('ref');
        });

        Schema::table('visit_sessions', function (Blueprint $t) {
            // last-touch: overwritten if this session's referrer carries one, otherwise inherited
            $t->string('search_term')->nullable()->after('ref');
        });
    }

    public function down(): void
    {
        Schema::table('visit_visitors', function (Blueprint $t) {
            $t->dropColumn('search_term');
        });

        Schema::table('visit_sessions', function (Blueprint $t) {
            $t->dropColumn('search_term');
        });
    }
};
