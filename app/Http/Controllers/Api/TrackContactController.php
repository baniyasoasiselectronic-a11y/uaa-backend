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
            'type' => ['required', 'in:whatsapp,call,assistant,assistant_contact'],
            'page' => ['nullable', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'max:64'],
            'text' => ['nullable', 'string', 'max:500'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            // what the visitor told the assistant they are looking for (all optional)
            'location' => ['nullable', 'string', 'max:80'],
            'looking_for' => ['nullable', 'string', 'max:60'],
            'move_in' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $session = $d['session'] ?? null;
        $page = isset($d['page']) ? Str::limit(preg_replace('/[?#].*$/', '', $d['page']), 250, '') : null;

        // A visitor chatting with the assistant chose to leave a name and number so the team can message them.
        if ($d['type'] === 'assistant_contact') {
            $name = trim(strip_tags((string) ($d['name'] ?? '')));
            $phone = trim((string) ($d['phone'] ?? ''));
            $digits = preg_replace('/\D+/', '', $phone);
            if (mb_strlen($name) < 2 || strlen($digits) < 8 || strlen($digits) > 15 || ! $session) {
                return response()->json(['message' => 'Please enter your name and a valid phone number.'], 422);
            }
            $enquiry = Enquiry::firstOrNew(['session_key' => $session, 'source' => 'ai_assistant']);
            if (! $enquiry->exists) {
                $enquiry->fill(['email' => null, 'page' => $page, 'message' => 'Chatted with the AI assistant.']);
            }
            $note = '✔ Left a name and number to be messaged on WhatsApp.';
            $enquiry->name = $name;
            $enquiry->phone = $phone;
            $enquiry->status = 'new';
            if (! str_contains((string) $enquiry->message, $note)) {
                $enquiry->message = trim($enquiry->message."\n".$note);
            }

            // preferred location, what they want and when, so the team can reply with the right units
            $clean = fn (?string $v, int $max) => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $v))), $max, '');
            $location = $clean($d['location'] ?? null, 80);
            $lookingFor = $clean($d['looking_for'] ?? null, 60);
            $moveIn = $clean($d['move_in'] ?? null, 60);
            $extra = $clean($d['note'] ?? null, 300);
            $details = array_filter([
                $location !== '' ? '📍 Preferred location: '.$location : null,
                $lookingFor !== '' ? '🏠 Looking for: '.$lookingFor : null,
                $moveIn !== '' ? '📅 Wants to move: '.$moveIn : null,
                $extra !== '' ? '💬 Note: '.$extra : null,
            ]);
            if ($details) {
                // a second submit in the same chat replaces the earlier details instead of piling up
                $msg = preg_replace('/\n?(📍 Preferred location|🏠 Looking for|📅 Wants to move|💬 Note): [^\n]*/u', '', (string) $enquiry->message);
                $enquiry->message = trim($msg."\n".implode("\n", $details));
                $enquiry->preferred_unit = Str::limit(trim(implode(' · ', array_filter([$location, $lookingFor]))), 250, '') ?: $enquiry->preferred_unit;
            }
            $enquiry->save();
            $enquiry->lead()->firstOrCreate([], ['name' => $name, 'email' => null, 'phone' => $phone, 'stage' => 'new']);

            return response()->json(['ok' => true], 201);
        }

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
