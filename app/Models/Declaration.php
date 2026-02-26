<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Declaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'exercicio',
        'ano_base',
        'tipo',
        'total_rend_tributaveis',
        'total_rend_exclusiva',
        'total_rend_recebidos_acumuladamente',
        'rend_trib_pj',
        'rend_trib_pf_exterior',
        'total_renda_isenta',
        'total_planos_saude',
        'total_despesas_medicas_odont',
        'total_despesas_instrucao',
        'total_pensao_judicial',
        'total_pgbl',
        'total_ir_pago',
        'total_imposto_pago_retido',
        'total_pagamentos_efetuados',
        'total_doacoes_efetuadas',
        'total_doacoes_partidos_politicos',
        'total_atividade_rural_resultado_tributavel',
        'total_renda_variavel',
        'gastos_declarados_total',
        'gastos_declarados_breakdown',
        'total_bens_imoveis',
        'total_dividas_onus',
        'total_bens_ano_anterior',
        'total_bens_ano_atual',
        'total_bens_reais',
        'total_dividas_ano_anterior',
        'total_dividas_ano_atual',
        'total_dividas_onus_reais',
        'total_bens_adquiridos_ano',
        'gastos_estimados',
        'variacao_patrimonial_descoberto',
        'risco_variacao_patrimonial',
        'inconsistencia_payload',
        'complexity_score',
        'complexity_level',
        'complexity_breakdown',
        'source_file_path',
        'last_is_retificadora',
        'last_recibo_anterior',
        'last_source_sha256',
        'last_imported_at',
        'imported_at',
    ];

    protected $casts = [
        'exercicio' => 'integer',
        'ano_base' => 'integer',
        'imported_at' => 'datetime',
        'total_rend_tributaveis' => 'decimal:2',
        'total_rend_exclusiva' => 'decimal:2',
        'total_rend_recebidos_acumuladamente' => 'decimal:2',
        'rend_trib_pj' => 'decimal:2',
        'rend_trib_pf_exterior' => 'decimal:2',
        'total_renda_isenta' => 'decimal:2',
        'total_planos_saude' => 'decimal:2',
        'total_despesas_medicas_odont' => 'decimal:2',
        'total_despesas_instrucao' => 'decimal:2',
        'total_pensao_judicial' => 'decimal:2',
        'total_pgbl' => 'decimal:2',
        'total_ir_pago' => 'decimal:2',
        'total_imposto_pago_retido' => 'decimal:2',
        'total_pagamentos_efetuados' => 'decimal:2',
        'total_doacoes_efetuadas' => 'decimal:2',
        'total_doacoes_partidos_politicos' => 'decimal:2',
        'total_atividade_rural_resultado_tributavel' => 'decimal:2',
        'total_renda_variavel' => 'decimal:2',
        'gastos_declarados_total' => 'decimal:2',
        'gastos_declarados_breakdown' => 'array',
        'total_bens_imoveis' => 'decimal:2',
        'total_dividas_onus' => 'decimal:2',
        'total_bens_ano_anterior' => 'decimal:2',
        'total_bens_ano_atual' => 'decimal:2',
        'total_bens_reais' => 'decimal:2',
        'total_dividas_ano_anterior' => 'decimal:2',
        'total_dividas_ano_atual' => 'decimal:2',
        'total_dividas_onus_reais' => 'decimal:2',
        'total_bens_adquiridos_ano' => 'decimal:2',
        'gastos_estimados' => 'decimal:2',
        'variacao_patrimonial_descoberto' => 'decimal:2',
        'risco_variacao_patrimonial' => 'boolean',
        'inconsistencia_payload' => 'array',
        'complexity_score' => 'integer',
        'complexity_breakdown' => 'array',
        'last_is_retificadora' => 'boolean',
        'last_imported_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isentos()
    {
        return $this->hasMany(DeclarationIsento::class);
    }

    public function imports()
    {
        return $this->hasMany(DeclarationImport::class)->orderByDesc('imported_at');
    }
}
