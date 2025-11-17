<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'first_name',
        'last_name',
        'company',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'is_primary',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper to display nicely
    public function getDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->line1,
            $this->line2,
            trim($this->city . ' ' . $this->state),
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}
