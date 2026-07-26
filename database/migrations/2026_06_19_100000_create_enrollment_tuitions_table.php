<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_tuitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('duration_hours', 4, 1);
            $table->unsignedInteger('tuition_amount');
            $table->timestamps();

            $table->unique(['enrollment_id', 'duration_hours']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_tuitions');
    }
};
