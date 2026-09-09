<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations', 'receipt_no')) {
                $table->string('receipt_no', 20)->nullable()->after('note');
                $table->index('receipt_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('reconciliations', 'receipt_no')) {
                $table->dropIndex(['receipt_no']);
                $table->dropColumn('receipt_no');
            }
        });
    }
};
