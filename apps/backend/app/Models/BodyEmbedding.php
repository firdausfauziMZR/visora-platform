<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyEmbedding extends Model
{
    protected $fillable = [
        'event_id',
        'event_photo_id',
        'body_index',
        'source_face_index',
        'box',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'box' => 'array',
            'source_face_index' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(EventPhoto::class, 'event_photo_id');
    }
}
