<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lunar\Base\LunarUser as LunarUserInterface;
use Lunar\Base\Traits\LunarUser;

class User extends Authenticatable implements FilamentUser, LunarUserInterface
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LunarUser, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'image_use_consent_at',
        'image_use_consent_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'image_use_consent_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_admin;
    }

    public function approvedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user_access')
            ->withPivot('approved_at')
            ->withTimestamps()
            ->wherePivotNotNull('approved_at');
    }

    public function downloadUnlocks(): HasMany
    {
        return $this->hasMany(DownloadUnlock::class);
    }

    public function canAccessEvent(Event|int $event): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $eventId = $event instanceof Event ? $event->id : $event;

        return $this->approvedEvents()
            ->whereKey($eventId)
            ->exists();
    }
}
