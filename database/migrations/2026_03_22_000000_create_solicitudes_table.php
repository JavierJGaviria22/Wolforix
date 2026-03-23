<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            
            // Relación con contacto
            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->onDelete('cascade');
            
            // Campos principales
            $table->string('request_type'); // tipo de solicitud: status_check, price_inquiry, order_follow_up, etc
            $table->string('status')->default('pending'); // pending, in_progress, resolved, closed
            $table->string('priority')->default('medium'); // low, medium, high
            
            // Detalles
            $table->text('description')->nullable(); // descripción de la solicitud
            $table->text('response')->nullable(); // respuesta a la solicitud
            
            // Fechas importantes
            $table->timestamp('resolved_at')->nullable(); // cuando se resolvió
            $table->timestamp('due_date')->nullable(); // fecha límite
            
            // Auditoría
            $table->timestamps(); // created_at y updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
