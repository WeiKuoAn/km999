<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStandardTemplateItem extends Model
{
    public const KIND_CHECK = 'check';

    public const KIND_TABLE = 'table';

    protected $fillable = [
        'fee_standard_template_id',
        'kind',
        'label',
        'heading_color',
        'detail',
        'table_headers',
        'table_rows',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'table_headers' => 'array',
            'table_rows' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeeStandardTemplate::class, 'fee_standard_template_id');
    }

    public function isTable(): bool
    {
        return ($this->kind ?? self::KIND_CHECK) === self::KIND_TABLE;
    }

    /**
     * @return array{
     *   kind:string,
     *   label:string,
     *   detail:?string,
     *   heading_color:?string,
     *   table_headers:?list<string>,
     *   table_rows:?list<list<string>>
     * }
     */
    public function toExportArray(): array
    {
        if ($this->isTable()) {
            $headers = array_values(array_map(
                fn ($h): string => trim((string) $h),
                is_array($this->table_headers) ? $this->table_headers : [],
            ));
            $rows = [];
            foreach (is_array($this->table_rows) ? $this->table_rows : [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $cells = array_values(array_map(
                    fn ($cell): string => trim((string) $cell),
                    $row,
                ));
                // 對齊欄數
                while (count($cells) < count($headers)) {
                    $cells[] = '';
                }
                if (count($headers) > 0) {
                    $cells = array_slice($cells, 0, count($headers));
                }
                $rows[] = $cells;
            }

            return [
                'kind' => self::KIND_TABLE,
                'label' => (string) $this->label,
                'detail' => null,
                'heading_color' => $this->heading_color !== null && trim((string) $this->heading_color) !== ''
                    ? (string) $this->heading_color
                    : null,
                'table_headers' => $headers,
                'table_rows' => $rows,
            ];
        }

        return [
            'kind' => self::KIND_CHECK,
            'label' => (string) $this->label,
            'detail' => $this->detail !== null && trim((string) $this->detail) !== ''
                ? (string) $this->detail
                : null,
            'heading_color' => null,
            'table_headers' => null,
            'table_rows' => null,
        ];
    }
}
