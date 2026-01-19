<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplicationForm extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFormFactory> */
    use HasFactory;

    public const THANK_YOU_TEXT = 'text';
    public const THANK_YOU_HTML = 'html';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'use_availability',
        'thank_you_format',
        'thank_you_content',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'use_availability'  => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(ApplicationFormField::class)->orderBy('sort');
    }

    public function thankYouIsHtml(): bool
    {
        return $this->thank_you_format === self::THANK_YOU_HTML;
    }

    public function thankYouContent(): string
    {
        return (string) ($this->thank_you_content ?? '');
    }

    protected static function booted(): void
    {
        static::created(function (self $form) {
            // Always ensure we have a message field by default
            $form->fields()->firstOrCreate(
                ['key' => 'message'],
                [
                    'type' => 'textarea',
                    'label' => 'Why do you want to volunteer?',
                    'help_text' => null,
                    'is_required' => true,
                    'is_active' => true,
                    'sort' => 10,
                    'config' => [
                        'rows' => 5,
                        'min' => 10,
                        'max' => 5000,
                        'placeholder' => 'Share a bit about your heart to serve...',
                    ],
                ]
            );
        });

        static::saving(function (self $form) {
            if (! $form->slug && $form->name) {
                $form->slug = Str::slug($form->name);
            }
        });
    }
}
