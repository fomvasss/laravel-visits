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
            $t->text('url')->nullable();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
