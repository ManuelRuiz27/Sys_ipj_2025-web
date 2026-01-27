<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salud360Assignment extends Model
{
    use HasFactory;

    protected $table = 'salud360_assignments';

    protected $fillable = [
        'beneficiario_id',
        'psicologo_id',
        'assigned_by',
        'changed_by',
        'active',
        'assigned_at',
        'changed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'assigned_at' => 'datetime',
        'changed_at' => 'datetime',
    ];

    public function beneficiario()
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }

    public function psicologo()
    {
        return $this->belongsTo(User::class, 'psicologo_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

