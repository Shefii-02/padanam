<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'fallback_sms' => 'boolean',
            'last_test_ok' => 'boolean',
            'token' => 'encrypted',
            'text_body' => 'array',
            'document_body' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function for(string $purpose): ?self
    {
        return static::where('purpose', $purpose)->first();
    }

    public function isReady(): bool
    {
        return $this->enabled && $this->api_url && $this->token;
    }

    /** Masked token for the admin screen – the real token never leaves the server. */
    public function maskedToken(): ?string
    {
        if (! $this->token) {
            return null;
        }

        return str_repeat('•', 8).substr($this->token, -4);
    }
}
