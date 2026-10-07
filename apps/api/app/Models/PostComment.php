<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 單層留言（FR-B6.2）：≤500 字，作者與貼文作者可刪除；不做巢狀。 */
class PostComment extends Model
{
    use HasUuids;

    protected $fillable = ['post_id', 'author_id', 'body', 'status'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
