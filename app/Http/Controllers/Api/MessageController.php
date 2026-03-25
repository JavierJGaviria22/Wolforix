<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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

        if ($phone == '573236524637') {
            die();
        }

        /*
        |--------------------------------------------------------------------------
        | Detectar tipo de mensaje: texto o audio
        |--------------------------------------------------------------------------
        */

        $messageData = $data['data']['message'];
        $text = $messageData['conversation'] ?? null;
        $audioBase64 = $data['data']['message']['base64'] ?? null;
        $audioMessage = $messageData['audioMessage'] ?? null;
        $messageType = 'text';
        $audioDuration = null;

        // Determinar tipo de mensaje
        if ($audioMessage && $audioBase64) {
            $messageType = 'audio';
            $audioDuration = $audioMessage['seconds'] ?? null;
        }

        // Validar que hay un mensaje válido
        if (!$phone || (!$text && !$audioBase64)) {
            return response()->json([
                'error' => 'phone and message or audio required'
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

        $rows = DB::select("SELECT * FROM (
        SELECT 
            z.id, 
            CASE z.direction
                WHEN 'incoming' THEN 'cliente'
                WHEN 'outgoing' THEN 'asesor'
            END AS tipo,
            z.content 
        FROM last_messages_view z
        LEFT JOIN contacts c ON c.id = z.contact_id
        WHERE c.phone IN ($phone, '573241579494')
            AND z.conversation_id = $conversation->id
        ORDER BY z.id DESC
        LIMIT 10
    ) zz
    ORDER BY zz.id ASC;");

        $context = collect($rows)->map(function ($row) {
            return [
                'id' => $row->id,
                'role' => $row->tipo, // cliente / asesor
                'message' => $row->content,
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | AUDIO: Enviar base64 a n8n para transcripción
        |--------------------------------------------------------------------------
        */

        if ($messageType === 'audio' && $audioBase64) {
            $audioMimetype = $audioMessage['mimetype'] ?? 'audio/ogg; codecs=opus';

            // Enviar a n8n con el base64 para procesamiento
            Http::post('https://n8n.wolfora.cloud/webhook/audio', [
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'tag' => $contact->tag ?? 'default',
                'number' => $phone,
                'duration' => $audioDuration,
                'mime_type' => $audioMimetype,
                'audio_base64' => $audioBase64,
                'context' => json_encode($context)
            ]);

            return response()->json([
                'success' => true,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'message_type' => 'audio',
                'duration' => $audioDuration,
                'note' => 'Audio enviado a n8n para transcripción.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TEXTO: Guardar mensaje entrante
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'tag' => $contact->tag,
            'direction' => 'incoming',
            'message_type' => $messageType,
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
            'tag' => $contact->tag,
            'number' => $phone,
            'context' => json_encode($context)
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

    /*
    |--------------------------------------------------------------------------
    | Guardar transcripción de audio (desde n8n)
    |--------------------------------------------------------------------------
    */

    public function incomingTranscription(Request $request)
    {
        // Recibir la transcripción procesada del audio desde n8n
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'conversation_id' => 'required|exists:conversations,id',
            'transcription' => 'required|string',
            'audio_message_id' => 'nullable|integer'
        ]);

        $contactId = $validated['contact_id'];
        $conversationId = $validated['conversation_id'];
        $transcription = $validated['transcription'];
        $audioMessageId = $validated['audio_message_id'] ?? null;

        // Verificar que la conversación pertenece al contacto
        $conversation = Conversation::where('id', $conversationId)
            ->where('contact_id', $contactId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'error' => 'Conversation not found for this contact'
            ], 404);
        }

        // Guardar el texto transcrito como un mensaje de entrada
        $message = Message::create([
            'conversation_id' => $conversationId,
            'contact_id' => $contactId,
            'direction' => 'incoming',
            'message_type' => 'text',
            'content' => $transcription,
            'is_ai' => false,
            'tag' => Contact::find($contactId)->tag
        ]);

        // Actualizar última actividad de la conversación
        $conversation->update([
            'last_message_at' => now()
        ]);

        // Obtener el contexto de la conversación
        $contact = Contact::find($contactId);
        $phone = $contact->phone;

        $rows = DB::select("SELECT * FROM (
        SELECT 
            z.id, 
            CASE z.direction
                WHEN 'incoming' THEN 'cliente'
                WHEN 'outgoing' THEN 'asesor'
            END AS tipo,
            z.content 
        FROM last_messages_view z
        LEFT JOIN contacts c ON c.id = z.contact_id
        WHERE c.phone IN (?, '573241579494')
            AND z.conversation_id = ?
        ORDER BY z.id DESC
        LIMIT 10
    ) zz
    ORDER BY zz.id ASC;", [$phone, $conversationId]);

        $context = collect($rows)->map(function ($row) {
            return [
                'id' => $row->id,
                'role' => $row->tipo, // cliente / asesor
                'message' => $row->content,
            ];
        })->values();

        // Enviar a n8n para procesamiento IA
        Http::post('https://n8n.wolfora.cloud/webhook/mensaje', [
            'contact_id' => $contactId,
            'conversation_id' => $conversationId,
            'message' => $transcription,
            'tag' => $contact->tag,
            'number' => $phone,
            'context' => json_encode($context),
            'from_audio' => true,
            'audio_message_id' => $audioMessageId
        ]);

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'conversation_id' => $conversationId,
            'contact_id' => $contactId,
            'transcription_saved' => true
        ], 201);
    }
}