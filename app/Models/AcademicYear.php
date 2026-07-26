<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'year_code',
        'name',
        'is_current',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->year_code.'學年度');
    }
}
