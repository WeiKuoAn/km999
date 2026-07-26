<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->json('parent_phones')->nullable()->after('parent_phone');
            $table->string('address_city', 32)->nullable()->after('address');
            $table->string('address_district', 32)->nullable()->after('address_city');
            $table->string('address_zip', 8)->nullable()->after('address_district');
            $table->string('address_detail')->nullable()->after('address_zip');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'parent_phones',
                'address_city',
                'address_district',
                'address_zip',
                'address_detail',
            ]);
        });
    }
};
