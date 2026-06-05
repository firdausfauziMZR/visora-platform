<?php

namespace App\Services;

use App\Models\EventPhoto;
use RuntimeException;
use Symfony\Component\Process\Process;

class LocalMlProcess
{
    public function indexPhoto(EventPhoto $photo, string $absolutePhotoPath): array
    {
        $process = new Process([
            config('services.ml_process.python'),
            config('services.ml_process.script'),
            'index-photo',
            '--event-id',
            (string) $photo->event_id,
            '--photo-id',
            (string) $photo->id,
            '--photo-path',
            $absolutePhotoPath,
            '--filename',
            $photo->original_filename ?: basename($absolutePhotoPath),
        ]);

        $process->setWorkingDirectory(dirname((string) config('services.ml_process.script')));
        $process->setTimeout((int) config('services.ml_process.timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException(
                'Local ML process failed'
                .($process->getExitCode() !== null ? " with exit code {$process->getExitCode()}" : '')
                .($errorOutput !== '' ? ": {$errorOutput}" : '.')
            );
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Local ML process returned invalid JSON.');
        }

        return $payload;
    }

    public function searchSelfie(string $eventId, string $absoluteSelfiePath, ?string $filename = null): array
    {
        $process = new Process(array_values(array_filter([
            config('services.ml_process.python'),
            config('services.ml_process.script'),
            'search-selfie',
            '--event-id',
            $eventId,
            '--selfie-path',
            $absoluteSelfiePath,
            $filename ? '--filename' : null,
            $filename,
        ], fn ($value): bool => $value !== null)));

        $process->setWorkingDirectory(dirname((string) config('services.ml_process.script')));
        $process->setTimeout((int) config('services.ml_process.timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException(
                'Local ML process failed'
                .($process->getExitCode() !== null ? " with exit code {$process->getExitCode()}" : '')
                .($errorOutput !== '' ? ": {$errorOutput}" : '.')
            );
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Local ML process returned invalid JSON.');
        }

        return $payload;
    }
}
