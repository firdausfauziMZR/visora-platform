<?php

namespace App\Support;

class PhotoDownloadPackages
{
    public static function defaultCode(): string
    {
        return 'original';
    }

    /**
     * @return array<string, array{label: string, description: string, variant: string, price_cents: int}>
     */
    public static function all(): array
    {
        return collect(config('services.photo_checkout.packages', []))
            ->map(fn (array $package): array => [
                'label' => (string) ($package['label'] ?? 'Download'),
                'description' => (string) ($package['description'] ?? ''),
                'variant' => (string) ($package['variant'] ?? 'original'),
                'price_cents' => (int) ($package['price_cents'] ?? config('services.photo_checkout.price_cents')),
            ])
            ->all();
    }

    /**
     * @return array{label: string, description: string, variant: string, price_cents: int}
     */
    public static function get(?string $code): array
    {
        $packages = self::all();

        return $packages[$code ?: self::defaultCode()]
            ?? $packages[self::defaultCode()]
            ?? [
                'label' => 'Original Download',
                'description' => 'Full original file.',
                'variant' => 'original',
                'price_cents' => (int) config('services.photo_checkout.price_cents'),
            ];
    }

    public static function isValid(?string $code): bool
    {
        return array_key_exists((string) $code, self::all());
    }

    public static function formatPrice(int $priceCents): string
    {
        return sprintf('%s %.2f', config('services.photo_checkout.currency_code'), $priceCents / 100);
    }
}
