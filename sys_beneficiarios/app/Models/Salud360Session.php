<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Salud360Session extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'salud360_sessions';

    protected $fillable = [
        'beneficiario_id',
        'psicologo_id',
        'session_date',
        'session_number',
        'is_first',
        'motivo_consulta',
        'riesgo_suicida',
        'uso_sustancias',
        'next_session_date',
        'next_objective',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'next_session_date' => 'date',
        'is_first' => 'boolean',
        'riesgo_suicida' => 'boolean',
        'uso_sustancias' => 'boolean',
    ];

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }

    public function psicologo()
    {
        return $this->belongsTo(User::class, 'psicologo_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('salud360_sessions')
            ->logFillable()
            ->logOnlyDirty();
    }
}
