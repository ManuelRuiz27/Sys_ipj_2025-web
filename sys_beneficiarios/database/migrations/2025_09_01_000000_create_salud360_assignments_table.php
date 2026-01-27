<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salud360_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('beneficiario_id')
                ->constrained('beneficiarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('psicologo_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('assigned_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->dateTime('assigned_at');
            $table->dateTime('changed_at')->nullable();
            $table->timestamps();

            $table->unique('beneficiario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salud360_assignments');
    }
};

