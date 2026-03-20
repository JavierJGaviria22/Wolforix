<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SpamLog;

class MessageController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Incoming message (desde n8n / evolution)
    |--------------------------------------------------------------------------
    */

    public function incoming(Request $request)
    {
        $data = $request->all();

        // Extraer teléfono (sin el @s.whatsapp.net)
        $telefonoCompleto = $data['data']['key']['remoteJid'];
        $phone = explode('@', $telefonoCompleto)[0];

        // echo $phone;die();

        if ($phone == '573241579494') {
            die();
        }
        if ($phone !== '573193787211') {
            die();
        }

        // Extraer mensaje
        $text = $data['data']['message']['conversation'] ?? null;

        if (!$phone || !$text) {
            return response()->json([
                'error' => 'phone and message required'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Buscar o crear contacto
        |--------------------------------------------------------------------------
        */

        $contact = Contact::firstOrCreate(
            ['phone' => $phone],
            [
                'first_seen_at' => now()
            ]
        );

        $contact->update([
            'last_seen_at' => now()
        ]);

        /*
        |--------------------------------------------------------------------------
        | Anti spam simple
        |--------------------------------------------------------------------------
        */

        $spam = SpamLog::firstOrCreate(
            ['contact_id' => $contact->id],
            [
                'message_count' => 0,
                'window_start' => now()
            ]
        );

        if ($spam->window_start->diffInSeconds(now()) > 60) {

            $spam->update([
                'message_count' => 1,
                'window_start' => now()
            ]);
        } else {

            $spam->increment('message_count');

            if ($spam->message_count > 20) {

                return response()->json([
                    'spam' => true,
                    'message' => 'rate limit exceeded'
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Conversación activa
        |--------------------------------------------------------------------------
        */

        $conversation = Conversation::firstOrCreate(
            [
                'contact_id' => $contact->id,
                'status' => 'open'
            ],
            [
                'last_message_at' => now()
            ]
        );

        $conversation->update([
            'last_message_at' => now()
        ]);

        /*
        |--------------------------------------------------------------------------
        | Guardar mensaje entrante
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'incoming',
            'message_type' => 'text',
            'content' => $text,
            'is_ai' => false
        ]);

        /*
        |--------------------------------------------------------------------------
        | Obtener memoria conversación
        |--------------------------------------------------------------------------
        */

        $memory = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(function ($msg) {

                return [
                    'role' => $msg->direction === 'incoming' ? 'user' : 'assistant',
                    'content' => $msg->content
                ];
            });

        //llamar a n8n con el mensaje entrante del usuario
        Http::post('https://n8n.wolfora.cloud/webhook/mensaje', [
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'message' => $text,
            'number' => $phone
        ]);

        return response()->json([
            'success' => true,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'memory' => $memory
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guardar respuesta IA
    |--------------------------------------------------------------------------
    */

    public function outgoing(Request $request)
    {
        //recibir la peticion de n8n con la respuesta, guardar en db y ejecutar el envio del mensaje (evolution api)

        $conversationId = $request->input('conversation_id');
        $contactId = $request->input('contact_id');
        $text = $request->input('text');
        $number = $request->input('number');

        if (!$conversationId || !$contactId || !$text) {

            return response()->json([
                'error' => 'missing parameters'
            ], 422);
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'contact_id' => $contactId,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => $text,
            'is_ai' => true
        ]);

        Conversation::where('id', $conversationId)
            ->update([
                'last_message_at' => now()
            ]);

        //llamar a evoluton api enviando el mensaje al cliente
        Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => 'supersecreta123',
        ])->post('https://wpp.wolfora.cloud/message/sendText/wpp-test', [
            'number' => $number,
            'text' => $text
        ]);

        return response()->json([
            'success' => true,
            'message_id' => $message->id
        ]);
    }
}
