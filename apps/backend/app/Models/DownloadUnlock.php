<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadUnlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_photo_id',
        'user_id',
        'order_reference',
        'customer_email',
        'package_code',
        'download_variant',
        'price_cents',
        'status',
        'unlocked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'unlocked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(EventPhoto::class, 'event_photo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
