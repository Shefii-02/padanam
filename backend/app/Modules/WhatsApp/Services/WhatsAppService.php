<?php

namespace App\Modules\WhatsApp\Services;

use App\Core\Support\DomainException;
use App\Core\Support\Settings;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic WhatsApp sender.
 * The admin saves the API URL, token and a request-body template in the database (whatsapp_accounts);
 * placeholders {{phone}} {{phone_local}} {{message}} {{file_url}} {{file_name}} {{caption}} {{otp}} {{token}} are filled per message.
 * Works with UltraMsg, Meta Cloud API, Interakt/WATI style gateways or any custom HTTP API.
 */
class WhatsAppService
{
    public const TEMPLATE_DEFAULTS = [
        'payment_link' => "Hi {name} 👋\nHere is your payment link for *{course}* ({batch}).\nAmount: *{amount}*\n{link}\nYour course unlocks automatically in the Padanam app after payment.",
        'invoice' => "Hi {name}, thank you for joining *{course}* on Padanam! 🎉\nYour invoice {invoice_no} for {amount} is attached.",
        'admission' => "Welcome to Padanam, {name}! 🎓\nYou are now enrolled in *{course}* ({batch}).\nLog in to the app with your number {phone}.",
        'swap' => "Hi {name}, your course has been changed from *{old_course}* to *{course}* ({batch}). Open the Padanam app to continue learning.",
        'swap_payment' => "Hi {name}, to move to *{course}* ({batch}) please pay the difference of *{amount}*:\n{link}\nYour new course unlocks automatically after payment.",
    ];

    public const VARIABLES = [
        'payment_link' => ['name', 'phone', 'course', 'batch', 'amount', 'link', 'order_no'],
        'invoice' => ['name', 'phone', 'course', 'batch', 'amount', 'invoice_no', 'invoice_url', 'order_no'],
        'admission' => ['name', 'phone', 'course', 'batch', 'amount', 'order_no'],
        'swap' => ['name', 'phone', 'course', 'batch', 'old_course', 'old_batch'],
        'swap_payment' => ['name', 'phone', 'course', 'batch', 'amount', 'link', 'old_course'],
    ];

    public static function presets(): array
    {
        return [
            'generic' => [
                'label' => 'Generic JSON API', 'auth_type' => 'bearer', 'auth_key' => 'token', 'body_format' => 'json', 'success_path' => null,
                'api_url' => 'https://your-provider.com/api/send', 'document_url' => null,
                'text_body' => ['to' => '{{phone}}', 'message' => '{{message}}'],
                'document_body' => ['to' => '{{phone}}', 'file_url' => '{{file_url}}', 'file_name' => '{{file_name}}', 'caption' => '{{caption}}'],
            ],
            'ultramsg' => [
                'label' => 'UltraMsg', 'auth_type' => 'body', 'auth_key' => 'token', 'body_format' => 'form', 'success_path' => 'sent',
                'api_url' => 'https://api.ultramsg.com/INSTANCE_ID/messages/chat', 'document_url' => 'https://api.ultramsg.com/INSTANCE_ID/messages/document',
                'text_body' => ['to' => '+{{phone}}', 'body' => '{{message}}'],
                'document_body' => ['to' => '+{{phone}}', 'filename' => '{{file_name}}', 'document' => '{{file_url}}', 'caption' => '{{caption}}'],
            ],
            'meta_cloud' => [
                'label' => 'Meta WhatsApp Cloud API', 'auth_type' => 'bearer', 'auth_key' => 'token', 'body_format' => 'json', 'success_path' => 'messages.0.id',
                'api_url' => 'https://graph.facebook.com/v20.0/PHONE_NUMBER_ID/messages', 'document_url' => null,
                'text_body' => ['messaging_product' => 'whatsapp', 'to' => '{{phone}}', 'type' => 'text', 'text' => ['body' => '{{message}}']],
                'document_body' => ['messaging_product' => 'whatsapp', 'to' => '{{phone}}', 'type' => 'document', 'document' => ['link' => '{{file_url}}', 'filename' => '{{file_name}}', 'caption' => '{{caption}}']],
            ],
            'meta_cloud_otp' => [
                'label' => 'Meta Cloud API – OTP template', 'auth_type' => 'bearer', 'auth_key' => 'token', 'body_format' => 'json', 'success_path' => 'messages.0.id',
                'api_url' => 'https://graph.facebook.com/v20.0/PHONE_NUMBER_ID/messages', 'document_url' => null,
                'text_body' => ['messaging_product' => 'whatsapp', 'to' => '{{phone}}', 'type' => 'template', 'template' => [
                    'name' => 'padanam_otp', 'language' => ['code' => 'en'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '{{otp}}']]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '{{otp}}']]],
                    ],
                ]],
                'document_body' => null,
            ],
        ];
    }

    public function account(string $purpose): WhatsAppAccount
    {
        return WhatsAppAccount::firstOrCreate(['purpose' => $purpose], [
            'provider' => 'generic',
            'text_body' => self::presets()['generic']['text_body'],
            'document_body' => $purpose === 'share' ? self::presets()['generic']['document_body'] : null,
            'message_template' => $purpose === 'otp' ? '{{otp}} is your Padanam login OTP. It is valid for 5 minutes. Do not share it with anyone.' : null,
        ]);
    }

    public function isReady(string $purpose): bool
    {
        return WhatsAppAccount::for($purpose)?->isReady() ?? false;
    }

    /** Message text from the editable templates (Settings → WhatsApp → Message content). */
    public function render(string $key, array $vars): string
    {
        $tpl = (Settings::get('whatsapp.templates') ?? [])[$key] ?? self::TEMPLATE_DEFAULTS[$key] ?? '';

        return strtr($tpl, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    public function sendText(string $phone, string $text, string $purpose, array $ctx = [], string $account = 'share'): WhatsAppMessage
    {
        $msg = WhatsAppMessage::create([
            'account' => $account, 'to_phone' => $phone, 'purpose' => $purpose, 'type' => 'text', 'body' => $text,
            'order_id' => $ctx['order_id'] ?? null, 'user_id' => $ctx['user_id'] ?? null, 'sent_by' => $ctx['sent_by'] ?? auth()->id(),
        ]);

        return $this->deliver($msg, ['message' => $text]);
    }

    public function sendDocument(string $phone, string $fileUrl, string $fileName, string $caption, string $purpose, array $ctx = []): WhatsAppMessage
    {
        $msg = WhatsAppMessage::create([
            'account' => 'share', 'to_phone' => $phone, 'purpose' => $purpose, 'type' => 'document', 'body' => $caption,
            'file_url' => $fileUrl, 'file_name' => $fileName,
            'order_id' => $ctx['order_id'] ?? null, 'user_id' => $ctx['user_id'] ?? null, 'sent_by' => $ctx['sent_by'] ?? auth()->id(),
        ]);

        return $this->deliver($msg, ['message' => $caption, 'caption' => $caption, 'file_url' => $fileUrl, 'file_name' => $fileName]);
    }

    /** OTP over WhatsApp. The code itself is never written to the message log. */
    public function sendOtp(string $phone, string $code, bool $test = false): WhatsAppMessage
    {
        $acc = $this->account('otp');
        $text = str_replace('{{otp}}', $code, $acc->message_template ?: '{{otp}} is your Padanam OTP.');
        $msg = WhatsAppMessage::create([
            'account' => 'otp', 'to_phone' => $phone, 'purpose' => $test ? 'test' : 'otp', 'type' => 'text',
            'body' => str_replace($code, '••••••', $text), 'sent_by' => $test ? auth()->id() : null,
        ]);

        return $this->deliver($msg, ['message' => $text, 'otp' => $code]);
    }

    public function retry(WhatsAppMessage $m): WhatsAppMessage
    {
        if ($m->account === 'otp') {
            throw new DomainException('OTP messages cannot be resent from here. The student can tap “Resend OTP”.');
        }

        return $this->deliver($m, ['message' => $m->body, 'caption' => $m->body, 'file_url' => $m->file_url, 'file_name' => $m->file_name]);
    }

    public function test(string $purpose, string $phone): WhatsAppMessage
    {
        $acc = $this->account($purpose);
        $msg = $purpose === 'otp'
            ? $this->sendOtp($phone, '123456', test: true)
            : $this->sendText($phone, '✅ Test message from Padanam admin – your WhatsApp API is connected.', 'test');
        $acc->update(['last_tested_at' => now(), 'last_test_ok' => $msg->status === 'sent']);

        return $msg;
    }

    protected function deliver(WhatsAppMessage $m, array $vars): WhatsAppMessage
    {
        $acc = WhatsAppAccount::for($m->account);
        $m->increment('attempts');
        if (! $acc || ! $acc->api_url || ! $acc->token) {
            $m->update(['status' => 'failed', 'error' => 'WhatsApp API is not set up (Settings → WhatsApp).']);

            return $m->fresh();
        }
        if (! $acc->enabled && $m->purpose !== 'test') {
            $m->update(['status' => 'failed', 'error' => 'WhatsApp API is turned off.']);

            return $m->fresh();
        }

        $digits = substr(preg_replace('/\D/', '', $m->to_phone), -10);
        $vars += ['caption' => $vars['message'] ?? '', 'file_url' => '', 'file_name' => '', 'otp' => ''];
        $vars['phone'] = $acc->country_code.$digits;
        $vars['phone_local'] = $digits;
        $vars['token'] = $acc->token;

        $template = $m->type === 'document' ? ($acc->document_body ?: $acc->text_body) : $acc->text_body;
        $url = $this->fill($m->type === 'document' ? ($acc->document_url ?: $acc->api_url) : $acc->api_url, $vars);
        $body = $this->fill($template ?? [], $vars);

        try {
            $req = Http::timeout(15)->acceptJson();
            switch ($acc->auth_type) {
                case 'bearer': $req = $req->withToken($acc->token);
                    break;
                case 'header': $req = $req->withHeaders([$acc->auth_key => $acc->token]);
                    break;
                case 'query': $url .= (str_contains($url, '?') ? '&' : '?').urlencode($acc->auth_key).'='.urlencode($acc->token);
                    break;
                case 'body': $body[$acc->auth_key] = $acc->token;
                    break;
            }
            $res = $acc->body_format === 'form' ? $req->asForm()->post($url, $body) : $req->post($url, $body);
            $ok = $res->successful() && (! $acc->success_path || data_get($res->json(), $acc->success_path));
            $m->update([
                'status' => $ok ? 'sent' : 'failed', 'http_status' => $res->status(), 'sent_at' => $ok ? now() : null,
                'response' => $this->redact(mb_substr($res->body(), 0, 2000), $acc->token, $vars['otp']),
                'error' => $ok ? null : 'Provider did not accept the message (HTTP '.$res->status().')',
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['id' => $m->id, 'error' => $e->getMessage()]);
            $m->update(['status' => 'failed', 'error' => mb_substr($this->redact($e->getMessage(), $acc->token, $vars['otp']), 0, 500)]);
        }

        return $m->fresh();
    }

    /** Replace {{placeholders}} in a string or (nested) array template. */
    public function fill(mixed $tpl, array $vars): mixed
    {
        if (is_array($tpl)) {
            return array_map(fn ($v) => $this->fill($v, $vars), $tpl);
        }
        if (! is_string($tpl)) {
            return $tpl;
        }

        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($x) => array_key_exists($x[1], $vars) ? (string) $vars[$x[1]] : $x[0], $tpl);
    }

    private function redact(string $s, ?string $token, ?string $otp): string
    {
        foreach (array_filter([$token, $otp]) as $secret) {
            $s = str_replace($secret, '***', $s);
        }

        return $s;
    }
}
