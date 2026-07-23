<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DirectoryEntry — staff-curated local resource directory (§5.8).
 */
class DirectoryEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category', 'name', 'area', 'url', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
