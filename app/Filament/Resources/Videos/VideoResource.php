<?php

namespace App\Filament\Resources\Videos;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Filament\Resources\Videos\Schemas\VideoForm;
use App\Filament\Resources\Videos\Tables\VideosTable;
use App\Models\Video;
use App\Services\Media\VideoMetadataExtractor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;
    private const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm'];

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-film';

    public static function form(Schema $schema): Schema
    {
        return VideoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VideosTable::configure($table);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function validateAndNormalize(array $data): array
    {
        $sourceType = (string) ($data['source_type'] ?? '');
        $path = static::normalizePathState($data['path'] ?? null);
        $externalUrl = (string) ($data['external_file_url'] ?? '');

        if ($sourceType === 'embed') {
            $embedUrl = (string) ($data['embed_url'] ?? '');
            if ($embedUrl === '' || ! filter_var($embedUrl, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'data.embed_url' => 'A valid embed URL is required.',
                ]);
            }

            if (! static::isAllowedEmbedUrl($embedUrl)) {
                throw ValidationException::withMessages([
                    'data.embed_url' => 'Only YouTube and Vimeo embed URLs are supported.',
                ]);
            }

            $data = static::nullUploadColumns($data);
        }

        if ($sourceType === 'upload') {
            $hasPath = filled($path);
            $hasExternalUrl = filled($externalUrl);

            if (! $hasPath && ! $hasExternalUrl) {
                throw ValidationException::withMessages([
                    'data.path' => 'Upload a video file or provide an external video URL.',
                    'data.external_file_url' => 'Upload a video file or provide an external video URL.',
                ]);
            }

            if ($hasPath && $hasExternalUrl) {
                throw ValidationException::withMessages([
                    'data.path' => 'Choose only one upload source: local file or external URL.',
                    'data.external_file_url' => 'Choose only one upload source: local file or external URL.',
                ]);
            }

            $data['embed_url'] = null;

            if ($hasExternalUrl) {
                if (! filter_var($externalUrl, FILTER_VALIDATE_URL)) {
                    throw ValidationException::withMessages([
                        'data.external_file_url' => 'Enter a valid external URL.',
                    ]);
                }

                $externalExtension = static::extractExtensionFromUrl($externalUrl);
                if ($externalExtension && ! in_array($externalExtension, self::ALLOWED_VIDEO_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'data.external_file_url' => 'External upload URLs must point to MP4 or WebM files.',
                    ]);
                }

                $data['public_url'] = $externalUrl;
                $data['disk'] = null;
                $data['path'] = null;
                $data['mime_type'] = null;
                $data['size_bytes'] = null;
                $data['duration_seconds'] = null;
                $data['extension'] = static::extractExtensionFromUrl($externalUrl);
            } else {
                $pathExtension = static::extractExtension($path);
                if (! $pathExtension || ! in_array($pathExtension, self::ALLOWED_VIDEO_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'data.path' => 'Uploaded files must be MP4 or WebM.',
                    ]);
                }

                $data['path'] = $path;
                $data['disk'] = (string) ($data['disk'] ?? 'public');
                $data['public_url'] = (string) ($data['public_url'] ?? static::makePublicUrl($path));

                $metadata = app(VideoMetadataExtractor::class)->extractFromDiskPath($data['disk'], $path);
                $data['mime_type'] = $metadata['mime_type'] ?? $data['mime_type'] ?? null;
                $data['extension'] = $metadata['extension'] ?? $data['extension'] ?? static::extractExtension($path);
                $data['size_bytes'] = $metadata['size_bytes'] ?? $data['size_bytes'] ?? null;
                $data['duration_seconds'] = $metadata['duration_seconds'] ?? $data['duration_seconds'] ?? null;
            }
        }

        unset($data['external_file_url'], $data['upload_status']);

        return $data;
    }

    private static function isAllowedEmbedUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return Str::contains($host, ['youtube.com', 'youtu.be', 'vimeo.com']);
    }

    private static function makePublicUrl(string $path): string
    {
        return url('/storage/' . ltrim($path, '/'));
    }

    private static function extractExtension(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? strtolower($extension) : null;
    }

    public static function extractExtensionFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return static::extractExtension($path);
    }

    private static function normalizePathState(mixed $pathState): string
    {
        if (is_array($pathState)) {
            $firstPath = reset($pathState);

            return is_string($firstPath) ? $firstPath : '';
        }

        return is_string($pathState) ? $pathState : '';
    }

    public static function clearUploadFields(Set $set): void
    {
        $set('path', null);
        $set('external_file_url', null);
        $set('disk', null);
        $set('public_url', null);
        $set('mime_type', null);
        $set('extension', null);
        $set('size_bytes', null);
        $set('duration_seconds', null);
    }

    private static function nullUploadColumns(array $data): array
    {
        $data['path'] = null;
        $data['disk'] = null;
        $data['public_url'] = null;
        $data['mime_type'] = null;
        $data['extension'] = null;
        $data['size_bytes'] = null;
        $data['duration_seconds'] = null;

        return $data;
    }

    private static function applyUploadMetadataFromPath(string $path, Set $set): void
    {
        $disk = 'public';
        $normalizedPath = ltrim($path, '/');

        $set('disk', $disk);
        $set('path', $normalizedPath);
        $set('public_url', static::makePublicUrl($normalizedPath));

        $metadata = app(VideoMetadataExtractor::class)->extractFromDiskPath($disk, $normalizedPath);
        $set('mime_type', $metadata['mime_type'] ?? null);
        $set('extension', $metadata['extension'] ?? static::extractExtension($normalizedPath));
        $set('size_bytes', $metadata['size_bytes'] ?? null);
        $set('duration_seconds', $metadata['duration_seconds'] ?? null);
    }

    public static function applyUploadMetadataFromPathState(mixed $pathState, Set $set): void
    {
        $normalizedPath = static::normalizePathState($pathState);

        if (blank($normalizedPath)) {
            return;
        }

        static::applyUploadMetadataFromPath($normalizedPath, $set);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/create'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }
}
