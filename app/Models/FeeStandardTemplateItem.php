<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStandardTemplateItem extends Model
{
    protected $fillable = [
        'fee_standard_template_id',
        'label',
        'detail',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeeStandardTemplate::class, 'fee_standard_template_id');
    }
}
