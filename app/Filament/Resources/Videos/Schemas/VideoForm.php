<?php

namespace App\Filament\Resources\Videos\Schemas;

use App\Filament\Resources\Videos\VideoResource;
use App\Models\Image;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_type')
                ->options([
                    'embed' => 'Embed URL',
                    'upload' => 'Uploaded File',
                ])
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state === 'embed') {
                        VideoResource::clearUploadFields($set);

                        return;
                    }

                    if ($state === 'upload') {
                        $set('embed_url', null);
                    }
                })
                ->helperText('Choose Embed URL for YouTube/Vimeo, or Uploaded File for local/CDN-hosted video files.')
                ->required(),

            Section::make('Embed Details')
                ->schema([
                    TextInput::make('embed_url')
                        ->url()
                        ->maxLength(500)
                        ->helperText('Use a YouTube or Vimeo embed URL. File metadata fields are not required for embeds.')
                        ->nullable(),
                ])
                ->visible(fn (Get $get): bool => $get('source_type') === 'embed'),

            Section::make('Upload Details')
                ->schema([
                    FileUpload::make('path')
                        ->label('Video File')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(512000)
                        ->directory('cms/videos/' . now()->format('Y/m'))
                        ->disk('public')
                        ->visibility('public')
                        ->nullable()
                        ->helperText('Upload MP4 or WebM up to 500 MB. For header/hero videos, aim for 25 MB or less and 6-12 seconds.')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            VideoResource::applyUploadMetadataFromPathState($state, $set);
                            $set('external_file_url', null);
                            $set('embed_url', null);
                        }),
                    TextInput::make('external_file_url')
                        ->label('External Video URL (optional)')
                        ->url()
                        ->maxLength(500)
                        ->helperText('Use this when the video file is hosted on a trusted CDN. Provide either this URL or an uploaded file, not both.')
                        ->nullable()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $url = (string) $state;
                            $set('public_url', $url);
                            $set('disk', null);
                            $set('path', null);
                            $set('mime_type', null);
                            $set('size_bytes', null);
                            $set('duration_seconds', null);
                            $set('extension', VideoResource::extractExtensionFromUrl($url));
                            $set('embed_url', null);
                        }),
                    ViewField::make('upload_status')
                        ->label(false)
                        ->dehydrated(false)
                        ->view('filament.forms.video-upload-status'),
                    Hidden::make('disk')
                        ->default('public'),
                    TextInput::make('public_url')
                        ->maxLength(500)
                        ->nullable()
                        ->visibleOn('edit')
                        ->helperText('Auto-generated after upload, or set from external URL. Editable on existing records.'),
                    TextInput::make('mime_type')
                        ->maxLength(120)
                        ->nullable()
                        ->visibleOn('edit')
                        ->helperText('Auto-detected for uploaded files when available. Editable on existing records.'),
                    TextInput::make('extension')
                        ->maxLength(20)
                        ->nullable()
                        ->visibleOn('edit'),
                    TextInput::make('size_bytes')
                        ->numeric()
                        ->nullable()
                        ->visibleOn('edit')
                        ->helperText('Auto-detected for uploaded files. Editable on existing records.'),
                    TextInput::make('duration_seconds')
                        ->numeric()
                        ->nullable()
                        ->visibleOn('edit')
                        ->helperText('Best-effort auto-detection. This may remain blank.'),
                ])
                ->visible(fn (Get $get): bool => $get('source_type') === 'upload'),

            Select::make('poster_image_id')
                ->label('Poster Image')
                ->options(Image::query()->orderByDesc('id')->limit(200)->pluck('title', 'id')->toArray())
                ->searchable()
                ->nullable(),
            TextInput::make('title')
                ->maxLength(255)
                ->nullable(),
            TextInput::make('alt_text')
                ->maxLength(255)
                ->nullable(),
            Textarea::make('description')
                ->columnSpanFull()
                ->nullable(),
            Toggle::make('is_decorative')
                ->default(false),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
