<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageGroupItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'image_group_id',
        'image_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->sort_order = max(0, (int) $item->sort_order);

            static::query()
                ->where('image_group_id', $item->image_group_id)
                ->where('sort_order', '>=', $item->sort_order)
                ->increment('sort_order');
        });

        static::updating(function (self $item): void {
            if (! $item->isDirty(['image_group_id', 'sort_order'])) {
                return;
            }

            $newGroupId = (int) $item->image_group_id;
            $newSort = max(0, (int) $item->sort_order);
            $oldGroupId = (int) $item->getOriginal('image_group_id');
            $oldSort = max(0, (int) $item->getOriginal('sort_order'));
            $currentId = (int) $item->id;

            $item->sort_order = $newSort;

            if ($newGroupId === $oldGroupId) {
                if ($newSort === $oldSort) {
                    return;
                }

                if ($newSort < $oldSort) {
                    static::query()
                        ->where('image_group_id', $newGroupId)
                        ->whereKeyNot($currentId)
                        ->whereBetween('sort_order', [$newSort, $oldSort - 1])
                        ->increment('sort_order');

                    return;
                }

                static::query()
                    ->where('image_group_id', $newGroupId)
                    ->whereKeyNot($currentId)
                    ->whereBetween('sort_order', [$oldSort + 1, $newSort])
                    ->decrement('sort_order');

                return;
            }

            // Close the gap in the previous group.
            static::query()
                ->where('image_group_id', $oldGroupId)
                ->where('sort_order', '>', $oldSort)
                ->decrement('sort_order');

            // Make room in the new group.
            static::query()
                ->where('image_group_id', $newGroupId)
                ->where('sort_order', '>=', $newSort)
                ->increment('sort_order');
        });

        static::deleted(function (self $item): void {
            static::query()
                ->where('image_group_id', $item->image_group_id)
                ->where('sort_order', '>', $item->sort_order)
                ->decrement('sort_order');
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ImageGroup::class, 'image_group_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
