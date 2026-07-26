<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('1=Mon ... 7=Sun');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['classroom_id', 'weekday']);
            $table->index(['weekday', 'start_time']);
        });

        DB::table('classrooms')
            ->whereNotNull('weekday')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $now = now();
                $insertRows = [];
                foreach ($rows as $row) {
                    $insertRows[] = [
                        'classroom_id' => $row->id,
                        'weekday' => $row->weekday,
                        'start_time' => $row->start_time,
                        'end_time' => $row->end_time,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($insertRows !== []) {
                    DB::table('classroom_schedules')->insert($insertRows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_schedules');
    }
};

