<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Stored as a calendar date in the location's own timezone.
            $table->date('date');
            $table->string('reason', 190)->nullable();
            $table->timestamps();

            // Also the lookup path for "is this local day closed?".
            $table->unique(['location_id', 'date'], 'closing_days_location_date_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_days');
    }
};
