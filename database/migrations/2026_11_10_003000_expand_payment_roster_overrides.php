<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_roster_overrides', function (Blueprint $table) {
            $table->json('course_ids')->nullable()->after('end_month')
                ->comment('名單覆寫科目');
            $table->json('course_months')->nullable()->after('course_ids')
                ->comment('各科月份 Y-M');
            $table->string('pay_cycle', 16)->nullable()->after('course_months');
            $table->json('material_half_charges')->nullable()->after('pay_cycle');
            $table->unsignedInteger('allowance')->default(0)->after('original_fee');
        });
    }

    public function down(): void
    {
        Schema::table('payment_roster_overrides', function (Blueprint $table) {
            $table->dropColumn([
                'course_ids',
                'course_months',
                'pay_cycle',
                'material_half_charges',
                'allowance',
            ]);
        });
    }
};
