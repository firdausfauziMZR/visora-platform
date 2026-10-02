<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    public const ACCESS_MODE_CODE_GRANTS_ACCESS = 'code_grants_access';

    public const ACCESS_MODE_CODE_CREATES_REQUEST = 'code_creates_request';

    public const ACCESS_MODE_MANUAL = 'manual';

    protected $fillable = [
        'name',
        'slug',
        'access_code',
        'access_mode',
        'starts_at',
        'ends_at',
        'venue',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->access_code = static::normalizeAccessCode($event->access_code)
                ?: static::generateAccessCode();
            $event->access_mode = static::normalizeAccessMode($event->access_mode);
        });

        static::saving(function (Event $event): void {
            $event->access_code = static::normalizeAccessCode($event->access_code);
            $event->access_mode = static::normalizeAccessMode($event->access_mode);
        });
    }

    /**
     * @return array<string, string>
     */
    public static function accessModeOptions(): array
    {
        return [
            self::ACCESS_MODE_CODE_GRANTS_ACCESS => 'QR/code grants access',
            self::ACCESS_MODE_CODE_CREATES_REQUEST => 'QR/code creates request',
            self::ACCESS_MODE_MANUAL => 'Manual approval only',
        ];
    }

    public static function normalizeAccessMode(?string $mode): string
    {
        return array_key_exists((string) $mode, self::accessModeOptions())
            ? (string) $mode
            : self::ACCESS_MODE_CODE_GRANTS_ACCESS;
    }

    public static function normalizeAccessCode(?string $code): ?string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', (string) $code);
        $normalized = Str::upper($normalized ?? '');

        return $normalized === '' ? null : $normalized;
    }

    public static function generateAccessCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::query()->where('access_code', $code)->exists());

        return $code;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    public function indexingJobs(): HasMany
    {
        return $this->hasMany(PhotoIndexingJob::class);
    }

    public function faceEmbeddings(): HasMany
    {
        return $this->hasMany(FaceEmbedding::class);
    }

    public function bodyEmbeddings(): HasMany
    {
        return $this->hasMany(BodyEmbedding::class);
    }

    public function approvedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user_access')
            ->withPivot('approved_at')
            ->withTimestamps()
            ->wherePivotNotNull('approved_at');
    }

    public function pendingAccessRequests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user_access')
            ->withPivot('approved_at')
            ->withTimestamps()
            ->wherePivotNull('approved_at');
    }

    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $this->approvedUsers()
            ->whereKey($user->id)
            ->exists();
    }
}
