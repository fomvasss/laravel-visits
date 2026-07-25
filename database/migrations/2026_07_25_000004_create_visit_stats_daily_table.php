<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('visit_stats_daily', function (Blueprint $t) {
            $t->id();
            $t->date('date')->index();
            // stored as empty string (not NULL) when there is no tenant — keeps the unique index reliable,
            // since NULL != NULL in a MySQL unique index and would allow duplicate rows.
            $t->string('tenant_id')->default('');
            $t->string('metric', 40); // sessions | visitors | page_views | conversions
            // dimension/dimension_value default to '' (not NULL) for the same reason as tenant_id above —
            // '' represents the "total, no breakdown" row.
            $t->string('dimension', 40)->default(''); // utm_source | referrer_host | country_code | ... | '' (total)
            $t->string('dimension_value')->default('');
            $t->unsignedBigInteger('count')->default(0);

            $t->unique(['date', 'tenant_id', 'metric', 'dimension', 'dimension_value'], 'visit_stats_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_stats_daily');
    }
};
