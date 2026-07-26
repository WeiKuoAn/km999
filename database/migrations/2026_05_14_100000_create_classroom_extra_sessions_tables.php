<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classroom_extra_sessions')) {
            Schema::create('classroom_extra_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
                $table->date('session_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();

                $table->unique(['classroom_id', 'session_date']);
            });
        }

        if (! Schema::hasTable('classroom_extra_session_student')) {
            Schema::create('classroom_extra_session_student', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_extra_session_id')
                    ->constrained('classroom_extra_sessions', 'id', 'cess_pivot_session_fk')
                    ->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->unique(['classroom_extra_session_id', 'student_id'], 'extra_session_student_unique');
            });
        }

        if (! Schema::hasColumn('classrooms', 'extra_sessions')) {
            return;
        }

        $normalize = function (mixed $time): string {
            $time = trim((string) $time);
            if ($time === '') {
                return '';
            }
            if (strlen($time) === 5) {
                return $time.':00';
            }

            return $time;
        };

        DB::table('classrooms')->orderBy('id')->chunkById(50, function ($rows) use ($normalize): void {
            foreach ($rows as $row) {
                $raw = $row->extra_sessions ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $list = is_string($raw) ? json_decode($raw, true) : $raw;
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $ex) {
                    if (! is_array($ex) || empty($ex['date'])) {
                        continue;
                    }
                    try {
                        $dateYmd = Carbon::parse($ex['date'])->toDateString();
                    } catch (Throwable) {
                        continue;
                    }
                    $st = $normalize($ex['start_time'] ?? '');
                    $en = $normalize($ex['end_time'] ?? '');
                    if ($st === '' || $en === '' || $st >= $en) {
                        continue;
                    }
                    $exists = DB::table('classroom_extra_sessions')
                        ->where('classroom_id', $row->id)
                        ->whereDate('session_date', $dateYmd)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('classroom_extra_sessions')->insert([
                        'classroom_id' => $row->id,
                        'session_date' => $dateYmd,
                        'start_time' => $st,
                        'end_time' => $en,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        DB::table('classrooms')->update(['extra_sessions' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_extra_session_student');
        Schema::dropIfExists('classroom_extra_sessions');
    }
};
