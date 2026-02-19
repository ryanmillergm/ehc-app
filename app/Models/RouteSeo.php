<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteSeo extends Model
{
    use HasFactory;

    public const ROUTE_DONATIONS_SHOW = 'donations.show';
    public const ROUTE_PAGES_INDEX = 'pages.index';
    public const ROUTE_EMAILS_SUBSCRIBE = 'emails.subscribe';

    protected $fillable = [
        'route_key',
        'language_id',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'canonical_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        return [
            self::ROUTE_DONATIONS_SHOW => 'Give Page (/give)',
            self::ROUTE_PAGES_INDEX => 'Pages Index (/pages)',
            self::ROUTE_EMAILS_SUBSCRIBE => 'Email Subscribe (/emails/subscribe)',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForRouteKey(Builder $query, string $routeKey): void
    {
        $query->where('route_key', $routeKey);
    }
}
