<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class VideoMetadataExtractor
{
    /**
     * @return array{mime_type:?string,extension:?string,size_bytes:?int,duration_seconds:?int}
     */
    public function extractFromDiskPath(string $disk, string $path): array
    {
        $normalizedPath = ltrim($path, '/');
        $storage = Storage::disk($disk);

        if (! $storage->exists($normalizedPath)) {
            return [
                'mime_type' => null,
                'extension' => $this->extractExtension($normalizedPath),
                'size_bytes' => null,
                'duration_seconds' => null,
            ];
        }

        $mimeType = null;
        $sizeBytes = null;
        $durationSeconds = null;

        try {
            $mimeType = $storage->mimeType($normalizedPath) ?: null;
        } catch (Throwable) {
            $mimeType = null;
        }

        try {
            $size = $storage->size($normalizedPath);
            $sizeBytes = is_numeric($size) ? (int) $size : null;
        } catch (Throwable) {
            $sizeBytes = null;
        }

        try {
            $absolutePath = $storage->path($normalizedPath);
            $durationSeconds = $this->probeDuration($absolutePath);
        } catch (Throwable) {
            $durationSeconds = null;
        }

        return [
            'mime_type' => $mimeType,
            'extension' => $this->extractExtension($normalizedPath),
            'size_bytes' => $sizeBytes,
            'duration_seconds' => $durationSeconds,
        ];
    }

    private function extractExtension(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? strtolower($extension) : null;
    }

    private function probeDuration(string $absolutePath): ?int
    {
        try {
            $process = new Process([
                'ffprobe',
                '-v',
                'error',
                '-show_entries',
                'format=duration',
                '-of',
                'default=noprint_wrappers=1:nokey=1',
                $absolutePath,
            ]);
            $process->setTimeout(3);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = trim($process->getOutput());
            if (! is_numeric($output)) {
                return null;
            }

            $seconds = (int) round((float) $output);

            return $seconds > 0 ? $seconds : null;
        } catch (Throwable) {
            return null;
        }
    }
}

