<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Solicitud;
use App\Models\Contact;

class SolicitudController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Guardar solicitud de cliente
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        // Validar datos
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'request_type' => 'required|string',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:baja,media,alta',
            'due_date' => 'nullable|date'
        ]);

        // Asignar valor por defecto a priority si no viene
        $validated['priority'] = $validated['priority'] ?? 'medium';

        // Asignar estado por defecto
        $validated['status'] = 'pending';

        // Crear la solicitud
        $solicitud = Solicitud::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud creada exitosamente',
            'solicitud' => $solicitud
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener solicitudes de un contacto
    |--------------------------------------------------------------------------
    */

    public function getByContact($contactId)
    {
        $solicitudes = Solicitud::where('contact_id', $contactId)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($solicitudes->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay solicitudes para este contacto',
                'solicitudes' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'solicitudes' => $solicitudes
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener todas las solicitudes
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $solicitudes = Solicitud::with('contact')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'solicitudes' => $solicitudes
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener una solicitud específica
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $solicitud = Solicitud::with('contact')->find($id);

        if (!$solicitud) {
            return response()->json([
                'error' => 'Solicitud no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'solicitud' => $solicitud
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar estado de solicitud
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, $id)
    {
        $solicitud = Solicitud::find($id);

        if (!$solicitud) {
            return response()->json([
                'error' => 'Solicitud no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,resolved,closed',
            'response' => 'nullable|string'
        ]);

        // Si el estado es resuelto, agregar timestamp
        if ($validated['status'] === 'resolved') {
            $validated['resolved_at'] = now();
        }

        $solicitud->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud actualizada exitosamente',
            'solicitud' => $solicitud
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener solicitudes pendientes
    |--------------------------------------------------------------------------
    */

    public function pending()
    {
        $solicitudes = Solicitud::pending()
            ->with('contact')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $solicitudes->count(),
            'solicitudes' => $solicitudes
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Eliminar solicitud
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $solicitud = Solicitud::find($id);

        if (!$solicitud) {
            return response()->json([
                'error' => 'Solicitud no encontrada'
            ], 404);
        }

        $solicitud->delete();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud eliminada exitosamente'
        ]);
    }
}
