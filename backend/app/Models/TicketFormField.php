<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TicketFormField extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'field_key', 'label', 'field_type', 'is_visible', 'is_required',
        'is_system', 'sort_order', 'options', 'default_value', 'placeholder',
        'section', 'help_text', 'role_visibility',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_required' => 'boolean',
        'is_system' => 'boolean',
        'options' => 'array',
        'role_visibility' => 'array',
    ];
}
