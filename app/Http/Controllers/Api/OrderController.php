<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function new(Request $request)
    {
        $order = Order::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pedido creado correctamente',
            'order_id' => $order->id
        ]);
    }
}