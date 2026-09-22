<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'type',
        'base_url',
        'api_key',
        'model',
        'enabled',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'api_key'    => 'encrypted',
        'enabled'    => 'boolean',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Unique id used throughout the AI services for this custom provider. */
    public function providerKey(): string
    {
        return 'custom:' . $this->id;
    }
}
