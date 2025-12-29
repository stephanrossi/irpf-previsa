<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'cpf',
        'nome',
    ];

    public function declarations()
    {
        return $this->hasMany(Declaration::class)->orderByDesc('ano_base');
    }

    public function getMaskedCpfAttribute(): string
    {
        $digits = str_pad(preg_replace('/\D/', '', $this->cpf), 11, '0', STR_PAD_LEFT);

        return sprintf('***.***.***-%s', substr($digits, -2));
    }

    public function getHasRiskAttribute(): bool
    {
        return $this->declarations()->where('risco_variacao_patrimonial', true)->exists();
    }
}
