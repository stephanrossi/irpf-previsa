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

    public function getFormattedCpfAttribute(): string
    {
        $digits = str_pad(preg_replace('/\D/', '', $this->cpf), 11, '0', STR_PAD_LEFT);

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    public function getHasRiskAttribute(): bool
    {
        $table = 'declarations';
        $evolucaoPatrimonial = "COALESCE({$table}.total_bens_reais, 0)";
        $caixa = '('
            ."COALESCE({$table}.rend_trib_pj, 0)"
            ." + COALESCE({$table}.rend_trib_pf_exterior, 0)"
            ." + COALESCE({$table}.total_renda_isenta, 0)"
            ." + COALESCE({$table}.total_rend_exclusiva, 0)"
            ." + COALESCE({$table}.total_rend_recebidos_acumuladamente, 0)"
            ." + COALESCE({$table}.total_renda_variavel, 0)"
            ." + COALESCE({$table}.total_atividade_rural_resultado_tributavel, 0)"
            ." - COALESCE({$table}.total_pagamentos_efetuados, 0)"
            ." - COALESCE({$table}.total_doacoes_efetuadas, 0)"
            ." - COALESCE({$table}.total_doacoes_partidos_politicos, 0)"
            ." - COALESCE({$table}.total_dividas_onus_reais, 0)"
            ." - COALESCE({$table}.gastos_estimados, 0)"
            .')';

        return $this->declarations()
            ->whereRaw("({$evolucaoPatrimonial} > 0 AND {$caixa} < ({$evolucaoPatrimonial} * 0.2))")
            ->exists();
    }
}
