<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Faq — public FAQ entry (§5.1, issue 2.8). Straw-cost reference ranges are
 * pulled from rate_config on the page, not stored here.
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category', 'question', 'answer', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
