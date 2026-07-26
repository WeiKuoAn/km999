<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('price_tier_1')->nullable()->after('note');
            $table->string('price_tier_2')->nullable()->after('price_tier_1');
            $table->string('price_tier_3')->nullable()->after('price_tier_2');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['price_tier_1', 'price_tier_2', 'price_tier_3']);
        });
    }
};
