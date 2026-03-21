<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->id(); // auto incremental

            $table->string('nombre');
            $table->string('apellido');
            $table->string('celular');
            $table->string('departamento');
            $table->string('ciudad');
            $table->string('direccion');
            $table->string('correo')->nullable();
            $table->integer('cantidad');

            $table->string('estado')->default('Pendiente');

            $table->timestamps(); // created_at y updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
