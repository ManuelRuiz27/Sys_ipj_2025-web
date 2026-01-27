<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salud360_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('beneficiario_id')
                ->constrained('beneficiarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('psicologo_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('session_date');
            $table->unsignedInteger('session_number');
            $table->boolean('is_first')->default(false);
            $table->text('motivo_consulta')->nullable();
            $table->boolean('riesgo_suicida')->nullable();
            $table->boolean('uso_sustancias')->nullable();
            $table->date('next_session_date')->nullable();
            $table->text('next_objective')->nullable();
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['beneficiario_id', 'session_number']);
            $table->index('beneficiario_id');
            $table->index('psicologo_id');
            $table->index('session_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salud360_sessions');
    }
};

