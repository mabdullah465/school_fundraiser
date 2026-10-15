<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookInbox extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = 'received_at';

    protected $table = 'webhook_inbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> */
    public function decodedPayload(): array
    {
        return json_decode($this->payload, true, flags: JSON_THROW_ON_ERROR);
    }
}
