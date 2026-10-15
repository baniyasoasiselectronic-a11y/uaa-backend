<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Text message through D7 Networks (the same provider the old site used). Does nothing until D7_API_TOKEN is set. */
class D7Sms
{
    public static function send(?string $phone, string $text): bool
    {
        $token = (string) config('services.d7.token');
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($token === '' || strlen($digits) < 8) {
            return false;
        }
        // UAE numbers typed as 05xxxxxxxx become +9715xxxxxxxx; numbers already in international form are kept
        $to = str_starts_with($digits, '971') ? '+'.$digits : (str_starts_with($digits, '00') ? '+'.substr($digits, 2) : '+971'.ltrim($digits, '0'));

        try {
            $res = Http::timeout(15)->withToken($token)->acceptJson()->post('https://api.d7networks.com/messages/v1/send', [
                'messages' => [['channel' => 'sms', 'recipients' => [$to], 'content' => $text, 'msg_type' => 'text', 'data_coding' => 'text']],
                'message_globals' => ['originator' => (string) config('services.d7.sender', 'UAA')],
            ]);

            return $res->successful();
        } catch (Throwable $e) {
            Log::warning('D7 SMS failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
