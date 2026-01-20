<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'label',
        'help_text',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(FormFieldPlacement::class, 'form_field_id');
    }

    public function options(): array
    {
        return (array) data_get($this->config, 'options', []);
    }
}
