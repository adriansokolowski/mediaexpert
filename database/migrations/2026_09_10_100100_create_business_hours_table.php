<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // ISO-8601 day number: 1 = Monday ... 7 = Sunday.
            // A day with no row is closed, which is how Sunday is modelled.
            $table->unsignedTinyInteger('day_of_week');

            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            // Opening hours are read on every availability and booking request,
            // always for one location and (at most) one weekday.
            $table->index(['location_id', 'day_of_week'], 'business_hours_location_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
