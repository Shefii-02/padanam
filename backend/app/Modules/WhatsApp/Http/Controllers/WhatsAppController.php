<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Audit;
use App\Core\Support\Phone;
use App\Core\Support\Settings;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $wa) {}

    public function status()
    {
        return ApiResponse::ok([
            'share_ready' => $this->wa->isReady('share'), 'otp_ready' => $this->wa->isReady('otp'),
            'auto' => Settings::get('whatsapp.auto') + ['payment_link' => true, 'invoice' => true, 'admission' => false, 'swap' => true],
        ]);
    }

    /** Both connections (token masked), message content templates, auto-send switches and presets. */
    public function settings()
    {
        $present = fn (WhatsAppAccount $a) => [
            'purpose' => $a->purpose, 'provider' => $a->provider, 'enabled' => $a->enabled, 'api_url' => $a->api_url, 'document_url' => $a->document_url,
            'has_token' => (bool) $a->token, 'token_hint' => $a->maskedToken(), 'auth_type' => $a->auth_type, 'auth_key' => $a->auth_key,
            'body_format' => $a->body_format, 'text_body' => $a->text_body, 'document_body' => $a->document_body, 'success_path' => $a->success_path,
            'country_code' => $a->country_code, 'message_template' => $a->message_template, 'fallback_sms' => $a->fallback_sms,
            'last_tested_at' => $a->last_tested_at, 'last_test_ok' => $a->last_test_ok, 'ready' => $a->isReady(),
        ];
        $saved = Settings::get('whatsapp.templates') ?? [];

        return ApiResponse::ok([
            'share' => $present($this->wa->account('share')),
            'otp' => $present($this->wa->account('otp')),
            'templates' => collect(WhatsAppService::TEMPLATE_DEFAULTS)->map(fn ($def, $k) => [
                'key' => $k, 'text' => $saved[$k] ?? $def, 'default' => $def, 'variables' => WhatsAppService::VARIABLES[$k] ?? [],
            ])->values(),
            'auto' => Settings::get('whatsapp.auto') + ['payment_link' => true, 'invoice' => true, 'admission' => false, 'swap' => true],
            'presets' => WhatsAppService::presets(),
            'placeholders' => ['{{phone}}' => 'Number with country code (919876543210)', '{{phone_local}}' => '10-digit number', '{{message}}' => 'Message text',
                '{{file_url}}' => 'Public link of the PDF', '{{file_name}}' => 'File name', '{{caption}}' => 'Text sent with the file', '{{otp}}' => 'OTP code (OTP API only)', '{{token}}' => 'Your API token'],
        ]);
    }

    public function saveAccount(Request $r, string $purpose)
    {
        abort_unless(in_array($purpose, ['share', 'otp'], true), 404);
        $d = $r->validate([
            'provider' => 'required|string|max:30', 'enabled' => 'required|boolean',
            'api_url' => 'nullable|url|max:500|required_if:enabled,true', 'document_url' => 'nullable|url|max:500',
            'token' => 'nullable|string|max:2000', 'clear_token' => 'nullable|boolean',
            'auth_type' => 'required|in:bearer,header,query,body', 'auth_key' => 'required|string|max:60', 'body_format' => 'required|in:json,form',
            'text_body' => 'required|array', 'document_body' => 'nullable|array', 'success_path' => 'nullable|string|max:100',
            'country_code' => 'required|digits_between:1,4', 'message_template' => 'nullable|string|max:500', 'fallback_sms' => 'nullable|boolean',
        ]);
        if ($purpose === 'otp' && ! empty($d['enabled']) && ! str_contains(json_encode($d['text_body']).($d['message_template'] ?? ''), '{{otp}}')) {
            return ApiResponse::fail('The OTP message or request body must contain {{otp}}.', 422, ['errors' => ['message_template' => ['Add {{otp}} where the code should appear.']]]);
        }
        $acc = $this->wa->account($purpose);
        $token = $d['token'] ?? null;
        $clear = (bool) ($d['clear_token'] ?? false);
        unset($d['token'], $d['clear_token']);
        if ($token) {
            $d['token'] = $token;
        } elseif ($clear) {
            $d['token'] = null;
        }
        if (! empty($d['enabled']) && ! ($d['token'] ?? $acc->token)) {
            return ApiResponse::fail('Add the API token before turning this on.', 422, ['errors' => ['token' => ['Required when enabled.']]]);
        }
        $acc->update($d);
        Audit::log('whatsapp.settings', $acc, ['purpose' => $purpose, 'enabled' => $acc->enabled, 'token_changed' => (bool) $token || $clear]);

        return ApiResponse::ok(null, $purpose === 'otp' ? ($acc->enabled ? 'WhatsApp OTP is ON' : 'WhatsApp OTP is OFF – OTP goes by SMS') : 'WhatsApp API saved');
    }

    public function saveTemplates(Request $r)
    {
        $keys = array_keys(WhatsAppService::TEMPLATE_DEFAULTS);
        $d = $r->validate(['templates' => 'required|array', 'templates.*' => 'nullable|string|max:1000', 'auto' => 'nullable|array', 'auto.*' => 'boolean']);
        Settings::set('whatsapp.templates', array_intersect_key(array_filter($d['templates'], fn ($v) => $v !== null && $v !== ''), array_flip($keys)));
        if (isset($d['auto'])) {
            Settings::set('whatsapp.auto', array_intersect_key($d['auto'], array_flip(['payment_link', 'invoice', 'admission', 'swap'])));
        }

        return ApiResponse::ok(null, 'Message content saved');
    }

    public function test(Request $r, string $purpose)
    {
        abort_unless(in_array($purpose, ['share', 'otp'], true), 404);
        $phone = Phone::normalize($r->input('phone'));
        abort_unless($phone, 422, 'Enter a valid 10-digit mobile number.');
        $m = $this->wa->test($purpose, $phone);

        return $m->status === 'sent'
            ? ApiResponse::ok($this->row($m), 'Test message sent ✅')
            : ApiResponse::fail('Test failed: '.$m->error, 422, ['data' => $this->row($m)]);
    }

    /** Send any text to a number (support replies, reminders). */
    public function send(Request $r)
    {
        $r->merge(['phone' => Phone::normalize($r->input('phone')) ?? $r->input('phone')]);
        $d = $r->validate(['phone' => 'required|regex:/^[6-9]\d{9}$/', 'message' => 'required|string|max:2000']);
        $m = $this->wa->sendText($d['phone'], $d['message'], 'manual');

        return $m->status === 'sent' ? ApiResponse::ok($this->row($m), 'Sent on WhatsApp') : ApiResponse::fail('Not sent: '.$m->error, 422);
    }

    public function messages(Request $r)
    {
        $q = WhatsAppMessage::with('sender:id,name', 'order:id,order_no')
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('purpose'), fn ($w, $v) => $w->where('purpose', $v))
            ->when($r->query('account'), fn ($w, $v) => $w->where('account', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where('to_phone', 'like', "%$v%"))
            ->when($r->query('from'), fn ($w, $v) => $w->where('created_at', '>=', $v))
            ->when($r->query('to'), fn ($w, $v) => $w->where('created_at', '<=', \Illuminate\Support\Carbon::parse($v)->endOfDay()))
            ->latest('id');
        $page = $q->paginate(min(100, (int) $r->query('per_page', 25)));
        $today = WhatsAppMessage::whereDate('created_at', today());

        return ApiResponse::paginated($page->through(fn ($m) => $this->row($m)), [
            'summary' => [
                'today' => (clone $today)->count(), 'sent_today' => (clone $today)->where('status', 'sent')->count(),
                'failed_today' => (clone $today)->where('status', 'failed')->count(),
                'by_purpose' => WhatsAppMessage::where('created_at', '>=', now()->subDays(30))->selectRaw('purpose, count(*) c')->groupBy('purpose')->pluck('c', 'purpose'),
            ],
        ]);
    }

    public function retry(WhatsAppMessage $message)
    {
        $m = $this->wa->retry($message);

        return $m->status === 'sent' ? ApiResponse::ok($this->row($m), 'Sent') : ApiResponse::fail('Still failing: '.$m->error, 422);
    }

    private function row(WhatsAppMessage $m): array
    {
        return [
            'id' => $m->id, 'account' => $m->account, 'to_phone' => $m->to_phone, 'purpose' => $m->purpose, 'type' => $m->type, 'body' => $m->body,
            'file_name' => $m->file_name, 'file_url' => $m->file_url, 'status' => $m->status, 'http_status' => $m->http_status, 'error' => $m->error,
            'response' => $m->response, 'attempts' => $m->attempts, 'order_no' => $m->order?->order_no, 'sent_by' => $m->sender?->name,
            'sent_at' => $m->sent_at, 'created_at' => $m->created_at,
        ];
    }
}
