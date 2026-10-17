<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImport
{
    /** @var list<string> * 開頭＝必填 */
    public const HEADERS = [
        '*學年',
        '*年級',
        '*姓名',
        '性別',
        '學生電話',
        '家長稱謂1',
        '家長電話1',
        '家長稱謂2',
        '家長電話2',
        '兄弟稱謂1',
        '兄弟姓名1',
        '兄弟學校1',
        '兄弟年級1',
        '兄弟稱謂2',
        '兄弟姓名2',
        '兄弟學校2',
        '兄弟年級2',
        '畢業國小',
        '就讀學校',
        '班級',
        '身分證字號',
        '縣市',
        '鄉鎮市區',
        '郵遞區號',
        '詳細地址',
        '狀態',
        '備註',
    ];

    /** @var list<string> */
    private const SIBLING_RELATIONS = ['哥哥', '姊姊', '弟弟', '妹妹'];

    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<string>}
     */
    public static function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || $path === '') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['無法讀取上傳檔案。']];
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['無法讀取 Excel：'.$e->getMessage()]];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['檔案沒有資料。']];
        }

        $headerRow = array_shift($rows);
        $map = self::headerMap(array_map(
            fn ($cell): string => trim((string) ($cell ?? '')),
            is_array($headerRow) ? $headerRow : []
        ));

        if (! isset($map['姓名'], $map['年級'], $map['學年'])) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['表頭必須包含「*學年」「*年級」「*姓名」。請先下載範本再填寫。'],
            ];
        }

        $years = AcademicYear::query()->get()->keyBy(fn (AcademicYear $y): string => (string) $y->year_code);
        $yearsByName = AcademicYear::query()->get()->keyBy(fn (AcademicYear $y): string => $y->displayName());
        $defaultYear = AcademicYear::query()->where('is_current', true)->first()
            ?? AcademicYear::query()->orderByDesc('year_code')->first();
        $grades = GradeLevel::query()->where('is_active', true)->get()->keyBy('name');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // 含表頭
            if (! is_array($row)) {
                continue;
            }

            $cell = fn (string $key): string => trim((string) ($row[$map[$key] ?? -1] ?? ''));

            $name = $cell('姓名');
            $gradeName = $cell('年級');
            if ($name === '' && $gradeName === '') {
                continue;
            }
            if ($name === '') {
                $errors[] = "第 {$line} 列：缺少姓名。";
                $skipped++;

                continue;
            }
            if ($gradeName === '') {
                $errors[] = "第 {$line} 列（{$name}）：缺少年級。";
                $skipped++;

                continue;
            }

            $grade = $grades->get($gradeName);
            if ($grade === null) {
                $errors[] = "第 {$line} 列（{$name}）：找不到年級「{$gradeName}」。請填國一／國二／國三。";
                $skipped++;

                continue;
            }

            $yearLabel = $cell('學年');
            if ($yearLabel === '') {
                $errors[] = "第 {$line} 列（{$name}）：缺少學年。請填學年代碼（如 115）。";
                $skipped++;

                continue;
            }
            $year = $years->get($yearLabel)
                ?? $yearsByName->get($yearLabel)
                ?? null;
            if ($year === null) {
                $hint = $defaultYear !== null
                    ? "目前學年為 {$defaultYear->year_code}。"
                    : '';
                $errors[] = "第 {$line} 列（{$name}）：找不到學年「{$yearLabel}」。請填學年代碼（如 115）。{$hint}";
                $skipped++;

                continue;
            }

            $gender = $cell('性別');
            if ($gender !== '' && ! in_array($gender, ['男', '女'], true)) {
                $errors[] = "第 {$line} 列（{$name}）：性別請填「男」或「女」。";
                $skipped++;

                continue;
            }

            $status = self::normalizeStatus($cell('狀態'));
            if ($status === null) {
                $errors[] = "第 {$line} 列（{$name}）：狀態請填在學／停課／已畢業（或留白）。";
                $skipped++;

                continue;
            }

            $siblings = [];
            $siblingError = null;
            for ($i = 1; $i <= 2; $i++) {
                $relation = $cell("兄弟稱謂{$i}");
                $siblingName = $cell("兄弟姓名{$i}");
                $siblingSchool = $cell("兄弟學校{$i}");
                $siblingGrade = $cell("兄弟年級{$i}");
                if ($relation === '' && $siblingName === '' && $siblingSchool === '' && $siblingGrade === '') {
                    continue;
                }
                if ($relation !== '' && ! in_array($relation, self::SIBLING_RELATIONS, true)) {
                    $siblingError = "第 {$line} 列（{$name}）：兄弟稱謂{$i}請填哥哥／姊姊／弟弟／妹妹。";
                    break;
                }
                $siblings[] = [
                    'relation' => $relation,
                    'name' => $siblingName,
                    'school' => $siblingSchool,
                    'grade' => $siblingGrade,
                ];
            }
            if ($siblingError !== null) {
                $errors[] = $siblingError;
                $skipped++;

                continue;
            }

            $existing = Student::query()
                ->where('academic_year_id', $year->id)
                ->where('grade_level_id', $grade->id)
                ->where('name', $name)
                ->first();

            $parentPhones = [];
            for ($i = 1; $i <= 2; $i++) {
                $title = $cell("家長稱謂{$i}");
                $phone = $cell("家長電話{$i}");
                if ($title !== '' || $phone !== '') {
                    $parentPhones[] = ['title' => $title, 'phone' => $phone];
                }
            }

            $payload = [
                'name' => $name,
                'phone' => self::nullIfEmpty($cell('學生電話')),
                'parent_phones' => $parentPhones,
                'siblings' => $siblings,
                'graduate_school' => self::nullIfEmpty($cell('畢業國小')),
                'current_school' => self::nullIfEmpty($cell('就讀學校')),
                'class_name' => self::nullIfEmpty($cell('班級')),
                'id_number' => self::nullIfEmpty($cell('身分證字號')),
                'address_city' => self::nullIfEmpty($cell('縣市')),
                'address_district' => self::nullIfEmpty($cell('鄉鎮市區')),
                'address_zip' => self::nullIfEmpty($cell('郵遞區號')),
                'address_detail' => self::nullIfEmpty($cell('詳細地址')),
                'gender' => self::nullIfEmpty($gender),
                'status' => $status,
                'note' => self::nullIfEmpty($cell('備註')),
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
            ];

            $payload = self::preparePayload($payload);

            try {
                if ($existing !== null) {
                    // 同學年、同年級、同名 → 覆蓋基本資料（學號不變）
                    DB::transaction(function () use ($existing, $payload): void {
                        unset($payload['academic_year_id'], $payload['grade_level_id'], $payload['name']);
                        $existing->update($payload);
                    });
                    $updated++;
                } else {
                    DB::transaction(function () use ($payload, $year, $grade): void {
                        $payload['student_code'] = StudentCodeGenerator::allocate($year, $grade);
                        $payload['school_segment'] = null;
                        Student::query()->create($payload);
                    });
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "第 {$line} 列（{$name}）：寫入失敗（{$e->getMessage()}）。";
                $skipped++;
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public static function templateResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('學生匯入');

        foreach (self::HEADERS as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $header);
        }

        // 範例列
        $currentYear = AcademicYear::query()->where('is_current', true)->value('year_code')
            ?? AcademicYear::query()->orderByDesc('year_code')->value('year_code')
            ?? '115';
        $example = [
            $currentYear,
            '國一',
            '王小明',
            '男',
            '0912345678',
            '母親',
            '0987654321',
            '父親',
            '0911222333',
            '姊姊',
            '王小美',
            '某某國中',
            '國三',
            '',
            '',
            '',
            '',
            '某某國小',
            '某某國中',
            '101',
            '',
            '高雄市',
            '鳳山區',
            '830',
            '中正路1號',
            '在學',
            '',
        ];
        foreach ($example as $i => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'2', $value);
        }

        foreach (range(1, count(self::HEADERS)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $filename = '學生匯入範本.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int> key 不含前置 *
     */
    private static function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            $header = trim($header);
            if ($header === '') {
                continue;
            }
            $key = ltrim($header, '*');
            $map[$key] = $index;
        }

        return $map;
    }

    private static function normalizeStatus(string $raw): ?string
    {
        if ($raw === '') {
            return 'active';
        }

        return match ($raw) {
            '在學', '啟用', 'active' => 'active',
            '停課', '暫停', 'paused' => 'paused',
            '已畢業', '畢業', 'graduated' => 'graduated',
            default => null,
        };
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function preparePayload(array $validated): array
    {
        $phones = collect($validated['parent_phones'] ?? [])
            ->map(fn ($phone): array => [
                'title' => trim((string) ($phone['title'] ?? '')),
                'phone' => trim((string) ($phone['phone'] ?? '')),
            ])
            ->filter(fn (array $phone): bool => $phone['title'] !== '' || $phone['phone'] !== '')
            ->values()
            ->all();

        $validated['parent_phones'] = $phones !== [] ? $phones : null;
        $first = $phones[0] ?? null;
        $validated['parent_name'] = $first['title'] ?? null;
        $validated['parent_phone'] = $first['phone'] ?? null;

        $siblings = collect($validated['siblings'] ?? [])
            ->map(fn ($sibling): array => [
                'relation' => trim((string) ($sibling['relation'] ?? '')),
                'name' => trim((string) ($sibling['name'] ?? '')),
                'school' => trim((string) ($sibling['school'] ?? '')),
                'grade' => trim((string) ($sibling['grade'] ?? '')),
            ])
            ->filter(fn (array $sibling): bool => $sibling['name'] !== ''
                || $sibling['school'] !== ''
                || $sibling['grade'] !== '')
            ->values()
            ->all();
        $validated['siblings'] = $siblings !== [] ? $siblings : null;

        $zip = trim((string) ($validated['address_zip'] ?? ''));
        $city = trim((string) ($validated['address_city'] ?? ''));
        $district = trim((string) ($validated['address_district'] ?? ''));
        $detail = trim((string) ($validated['address_detail'] ?? ''));

        $validated['address_city'] = $city !== '' ? $city : null;
        $validated['address_district'] = $district !== '' ? $district : null;
        $validated['address_zip'] = $zip !== '' ? $zip : null;
        $validated['address_detail'] = $detail !== '' ? $detail : null;

        $addressParts = array_filter([$zip, $city.$district, $detail], fn (string $part): bool => $part !== '');
        $validated['address'] = $addressParts !== [] ? implode(' ', $addressParts) : null;

        return $validated;
    }
}
