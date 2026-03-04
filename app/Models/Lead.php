<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'view_number',
        'address_id',
        'title',
        'category',
        'subcategories',
        'district',
        'locality',
        'business_name',
        'mobile',
        'mobile_formatted',
        'whatsapp_enabled',
        'status',
        'raw_response',
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFetched($query)
    {
        return $query->where('status', 'fetched');
    }

    public function scopeWithWhatsApp($query)
    {
        return $query->where('whatsapp_enabled', true);
    }

    /**
     * Format mobile number for display (remove country code prefix for readability)
     */
    public function getFormattedMobileAttribute(): string
    {
        if (!$this->mobile_formatted) return 'N/A';
        // Strip leading 91 (India) if present and return clean number
        $num = $this->mobile_formatted;
        if (strlen($num) === 12 && str_starts_with($num, '91')) {
            return '+91 ' . substr($num, 2, 5) . ' ' . substr($num, 7);
        }
        return $num;
    }
}
