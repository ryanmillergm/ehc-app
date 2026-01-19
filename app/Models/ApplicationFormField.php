<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFormField extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFormFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'application_form_id',
        'type',
        'key',
        'label',
        'help_text',
        'is_required',
        'is_active',
        'sort',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'application_form_id');
    }

    public function options(): array
    {
        // For select/radio/checkbox_group
        return (array) data_get($this->config, 'options', []);
    }
}
