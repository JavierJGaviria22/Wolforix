<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function new(Request $request)
    {

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'required|string|max:20',
            'departamento' => 'required|string|max:100',
            'ciudad' => 'required|string|max:100',
            'direccion' => 'required|string|max:255',
            'correo' => 'nullable|email|max:150',
            'cantidad' => 'required|integer|min:1'
        ]);

        $order = Order::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado correctamente',
            'order_id' => $order->id
        ]);
    }
}