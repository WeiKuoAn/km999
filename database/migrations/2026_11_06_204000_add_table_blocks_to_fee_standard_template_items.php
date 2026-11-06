<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_standard_template_items', function (Blueprint $table) {
            $table->string('kind', 16)->default('check')->after('fee_standard_template_id')
                ->comment('check＝勾選項目；table＝收費表格');
            $table->string('heading_color', 32)->nullable()->after('label')
                ->comment('表格區塊標題色（例：#1d4ed8）');
            $table->json('table_headers')->nullable()->after('detail');
            $table->json('table_rows')->nullable()->after('table_headers');
        });
    }

    public function down(): void
    {
        Schema::table('fee_standard_template_items', function (Blueprint $table) {
            $table->dropColumn(['kind', 'heading_color', 'table_headers', 'table_rows']);
        });
    }
};
