<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderCall extends Model
{
    public const OUTCOME_OK = 'ok';

    public const OUTCOME_HTTP_ERROR = 'http_error';

    public const OUTCOME_TIMEOUT = 'timeout';

    public const OUTCOME_CONNECTION_ERROR = 'connection_error';

    public const OUTCOME_BLOCKED = 'blocked';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'operation',
        'provider',
        'model',
        'latency_ms',
        'attempts',
        'prompt_tokens',
        'completion_tokens',
        'outcome',
        'error_code',
    ];

    protected $casts = [
        'latency_ms' => 'integer',
        'attempts' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
