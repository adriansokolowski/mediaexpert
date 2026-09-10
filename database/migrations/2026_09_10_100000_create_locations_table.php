<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('slot_duration_minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
