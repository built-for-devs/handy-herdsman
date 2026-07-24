<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Redirect — 301 from an old Homestead Herds URL to a migrated post, created by
 * the content migration to preserve link equity (§8, §10b).
 */
class Redirect extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'from_path', 'post_id', 'to_url', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
