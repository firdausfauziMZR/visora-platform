<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Lunar\Base\Purchasable;
use Lunar\Base\Traits\HasPrices;
use Lunar\Models\Contracts\TaxClass as TaxClassContract;
use Lunar\Models\TaxClass;

class EventPhoto extends Model implements Purchasable
{
    use HasFactory;
    use HasPrices;

    protected $fillable = [
        'event_id',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'status',
        'tax_class_id',
        'faces_detected',
        'indexed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'faces_detected' => 'integer',
            'tax_class_id' => 'integer',
            'indexed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventPhoto $photo): void {
            if (! $photo->tax_class_id) {
                $photo->tax_class_id = TaxClass::getDefault()?->id
                    ?: TaxClass::query()->firstOrCreate(
                        ['default' => true],
                        ['name' => 'Default Tax Class'],
                    )->id;
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::modelClass());
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

    public function downloadUnlocks(): HasMany
    {
        return $this->hasMany(DownloadUnlock::class);
    }

    public function getPrices(): Collection
    {
        return $this->prices()->get();
    }

    public function getUnitQuantity(): int
    {
        return 1;
    }

    public function getTaxClass(): TaxClassContract
    {
        return $this->taxClass ?: TaxClass::getDefault() ?: TaxClass::query()->firstOrCreate(
            ['default' => true],
            ['name' => 'Default Tax Class'],
        );
    }

    public function getTaxReference(): ?string
    {
        return null;
    }

    public function getType(): string
    {
        return 'digital';
    }

    public function getDescription(): string
    {
        return $this->original_filename ?: "Event photo {$this->id}";
    }

    public function getOption(): ?string
    {
        return $this->event?->name;
    }

    public function getOptions(): Collection
    {
        return collect([$this->getOption()])->filter()->values();
    }

    public function getIdentifier(): string
    {
        return "VISORA-PHOTO-{$this->id}";
    }

    public function isShippable(): bool
    {
        return false;
    }

    public function getThumbnail(): string
    {
        return $this->variantPath('thumbnail')
            ?: $this->storage_path;
    }

    public function variantPath(string $variant): ?string
    {
        return data_get($this->metadata, "variants.{$variant}.path");
    }

    public function variantUrl(string $variant): ?string
    {
        return data_get($this->metadata, "variants.{$variant}.path");
    }

    public function canBeFulfilledAtQuantity(int $quantity): bool
    {
        return $quantity > 0 && $this->status === 'indexed';
    }

    public function getTotalInventory(): int
    {
        return $this->status === 'indexed' ? 1 : 0;
    }
}
