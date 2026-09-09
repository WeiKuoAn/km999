<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16)->comment('amount=固定金額；percent=折扣％');
            $table->unsignedInteger('value')->comment('金額元，或百分比 1–100');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations', 'fee_discount_id')) {
                $table->foreignId('fee_discount_id')
                    ->nullable()
                    ->after('receipt_no')
                    ->constrained('fee_discounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('reconciliations', 'fee_discount_id')) {
                $table->dropConstrainedForeignId('fee_discount_id');
            }
        });

        Schema::dropIfExists('fee_discounts');
    }
};
