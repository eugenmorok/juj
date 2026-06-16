<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'arena_setting_id',
    'user_id',
    'changed_fields',
    'before_values',
    'after_values',
    'note',
])]
class BalanceChangeLog extends Model
{
    /**
     * @return BelongsTo<ArenaSetting, $this>
     */
    public function arenaSetting(): BelongsTo
    {
        return $this->belongsTo(ArenaSetting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arena_setting_id' => 'integer',
            'user_id' => 'integer',
            'changed_fields' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}
