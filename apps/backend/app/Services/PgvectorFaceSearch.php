<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PgvectorFaceSearch
{
    public function __construct(private readonly LocalMlProcess $mlProcess) {}

    public function searchSelfie(
        int|string $eventId,
        string $absoluteSelfiePath,
        ?string $filename = null,
        ?int $limit = null,
        ?float $maxCosineDistance = null,
    ): array {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('pgvector face search requires the PostgreSQL connection.');
        }

        $payload = $this->mlProcess->searchSelfie((string) $eventId, $absoluteSelfiePath, $filename);
        $face = $payload['faces'][0] ?? null;
        $embedding = $face['embedding'] ?? null;

        if (! is_array($embedding)) {
            return [
                'status' => 'searched',
                'event_id' => (string) $eventId,
                'filename' => $payload['filename'] ?? $filename,
                'faces_detected' => $payload['faces_detected'] ?? 0,
                'matches' => [],
            ];
        }

        $vector = self::vectorLiteral($embedding);

        $limit ??= (int) config('services.photo_search.match_limit', 20);
        $maxCosineDistance ??= (float) config('services.photo_search.max_cosine_distance', 0.45);

        $matches = DB::select(
            <<<'SQL'
                select
                    event_photo_id as photo_id,
                    min(embedding <=> ?::vector) as cosine_distance
                from face_embeddings
                where event_id = ?
                group by event_photo_id
                having min(embedding <=> ?::vector) <= ?
                order by cosine_distance asc
                limit ?
            SQL,
            [$vector, $eventId, $vector, $maxCosineDistance, $limit],
        );

        return [
            'status' => 'searched',
            'event_id' => (string) $eventId,
            'filename' => $payload['filename'] ?? $filename,
            'faces_detected' => $payload['faces_detected'] ?? 0,
            'matches' => array_map(
                fn ($match): array => [
                    'photo_id' => (string) $match->photo_id,
                    'cosine_distance' => (float) $match->cosine_distance,
                ],
                $matches,
            ),
        ];
    }

    public static function vectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(
            fn ($value): string => (string) ((float) $value),
            $embedding,
        )).']';
    }
}
