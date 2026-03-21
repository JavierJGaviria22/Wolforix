<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Order;

class OrderController extends Controller
{
    public function new(Request $request)
    {
        $order = Order::create($request->all());

        $phone = $request->input('celular');
        $id_contact = Contact::where('phone', $phone)->first()->id ?? null;

        if ($id_contact) {
            Contact::where('id', $id_contact)->update(['tag' => 'Cliente']);
        }

        Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => 'supersecreta123',
        ])->post('https://wpp.wolfora.cloud/message/sendText/wpp-test', [
            'number' => $phone,
            'text' => '"¡Gracias por confiar en Wolfora Store! 🙌
• Tu pedido será despachado en las próximas 24 horas
• Te mantendremos informado del estado de tu pedido por este mismo medio

Si tienes alguna duda, no dudes en escribirnos 😊"'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado correctamente',
            'order_id' => $order->id
        ]);
    }
}