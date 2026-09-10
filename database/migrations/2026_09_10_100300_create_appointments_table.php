<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            // Auto-incrementing surrogate key: keeps the clustered index append-only
            // instead of scattering writes the way a random UUID primary key would.
            $table->id();

            // Public, non-guessable identifier used in URLs.
            $table->ulid('public_id')->unique();

            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // All instants are persisted in UTC. Business hours are evaluated in the
            // location's timezone before conversion.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Mirrors starts_at while the appointment is active and is set to NULL on
            // cancellation. Combined with the unique index below this makes "one active
            // booking per slot" a database invariant, while cancelled rows keep their
            // history and stop blocking the slot (NULLs are distinct in a unique index).
            $table->dateTime('active_starts_at')->nullable();

            $table->string('customer_name', 120)->nullable();
            $table->string('customer_email', 180)->nullable();

            $table->string('status', 16);
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            // Concurrency guard and the index backing availability lookups.
            $table->unique(['location_id', 'active_starts_at'], 'appointments_active_slot_unq');

            // Listing / date-range filtering across every status, ordered by start time.
            $table->index(['location_id', 'starts_at'], 'appointments_location_starts_idx');

            // "All appointments of this customer", newest or oldest first.
            $table->index(['customer_email', 'starts_at'], 'appointments_customer_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
