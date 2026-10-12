<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records the quick contacts that happen without a form, so they show up in the admin
 * Enquiries inbox next to the form enquiries:
 *  - whatsapp / call : a tap on the WhatsApp or phone icon (one entry per visitor, page and type, per 10 minutes)
 *  - assistant       : the questions a visitor asks the AI assistant (one entry per chat, growing as they ask)
 * No personal details are collected here — only the page and what was tapped or asked.
 */
class TrackContactController extends Controller
{
    private const LABELS = ['whatsapp' => 'WhatsApp icon', 'call' => 'Call icon'];

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'type' => ['required', 'in:whatsapp,call,assistant'],
            'page' => ['nullable', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'max:64'],
            'text' => ['nullable', 'string', 'max:500'],
        ]);
        $session = $d['session'] ?? null;
        $page = isset($d['page']) ? Str::limit(preg_replace('/[?#].*$/', '', $d['page']), 250, '') : null;

        if ($d['type'] === 'assistant') {
            $text = trim(strip_tags((string) ($d['text'] ?? '')));
            if ($text === '' || ! $session) {
                return response()->json(['ok' => true]);
            }
            $enquiry = Enquiry::where('session_key', $session)->where('source', 'ai_assistant')->first();
            if ($enquiry) {
                if (str_contains((string) $enquiry->message, '• '.$text) === false && strlen((string) $enquiry->message) < 3000) {
                    $enquiry->update(['message' => $enquiry->message."\n• ".$text]);
                }
            } else {
                Enquiry::create([
                    'name' => 'Website visitor (AI assistant)',
                    'email' => null,
                    'phone' => null,
                    'source' => 'ai_assistant',
                    'session_key' => $session,
                    'page' => $page,
                    'message' => "Asked the AI assistant:\n• ".$text,
                ]);
            }

            return response()->json(['ok' => true]);
        }

        $recent = Enquiry::where('source', $d['type'].'_click')
            ->where('page', $page)
            ->where('session_key', $session)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
        if (! $recent) {
            Enquiry::create([
                'name' => 'Website visitor ('.self::LABELS[$d['type']].')',
                'email' => null,
                'phone' => null,
                'source' => $d['type'].'_click',
                'session_key' => $session,
                'page' => $page,
                'message' => 'Tapped the '.self::LABELS[$d['type']].' on '.($page ?: 'the website').'.',
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
