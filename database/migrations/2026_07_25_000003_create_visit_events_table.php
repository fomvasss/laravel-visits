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
            $t->foreignId('session_id')->constrained('visit_sessions')->cascadeOnDelete();
            // denormalized for convenient querying without joining through session
            $t->foreignId('visitor_id')->constrained('visit_visitors')->cascadeOnDelete();

            $t->string('type', 20)->index(); // page_view | action
            $t->string('action')->nullable()->index(); // free string, e.g. 'order.placed', 'lead.created'

            $t->text('url')->nullable();

            $t->boolean('is_bot')->default(false)->index();
            $t->string('bot_name')->nullable();
            $t->string('bot_category')->nullable();

            $t->nullableMorphs('eventable'); // link to host business model (Order/Lead/Comment/...)

            $t->json('meta')->nullable();

            $t->timestamp('created_at')->index();

            $t->index(['session_id', 'created_at']);
            $t->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
