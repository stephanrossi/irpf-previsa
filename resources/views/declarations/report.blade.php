@extends('layouts.app')

@section('title', 'Relatorio de Inconsistencia')

@section('content')
    @php
        $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $rendaTrib = (float) $declaration->total_rend_tributaveis;
        $rendaIsenta = (float) $declaration->total_renda_isenta;
        $gastosEstimados = $declaration->gastos_estimados ?? 0;
        $gastosDeclarados = (float) $declaration->gastos_declarados_total;

        $entradasSaidas = (float) ($riskMetrics['entradas_saidas'] ?? 0);
        $evolucaoPatrimonial = (float) ($riskMetrics['evolucao_patrimonial'] ?? 0);
        $caixaTotal = (float) ($riskMetrics['caixa_total'] ?? 0);
        $limiteRisco = (float) ($riskMetrics['limite_risco'] ?? 0);
        $risco = (bool) ($riskMetrics['risco'] ?? false);
    @endphp

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-600">Cliente</div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $declaration->client->nome }}</h1>
                <div class="text-sm text-slate-600">CPF: {{ $declaration->client->formatted_cpf }}</div>
                <div class="text-sm text-slate-600">IR {{ $declaration->exercicio }} - Ano base {{ $declaration->ano_base }}</div>
            </div>
            <div class="flex items-center gap-3">
                @if ($declaration->last_is_retificadora)
                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">
                        Retificadora
                        @if ($declaration->last_recibo_anterior)
                            <span class="text-indigo-600">Recibo anterior: {{ $declaration->last_recibo_anterior }}</span>
                        @endif
                    </span>
                @endif
                @if ($risco)
                    <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                        EM RISCO - Caixa abaixo de 20% da Evolucao Patrimonial
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                        OK - Regra de risco nao acionada
                    </span>
                @endif
                <a href="{{ route('clients.show', $declaration->client) }}" class="text-sm text-slate-600 hover:text-slate-900">&larr; Voltar</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Rendimentos tributaveis</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($rendaTrib) }}</div>
                <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm text-slate-600">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
                        <span>Recebimentos PJ (Rend PJ - Prev Oficial - IR Fonte + 13o - IR 13o)</span>
                        <span class="whitespace-nowrap text-right font-medium tabular-nums text-slate-800">{{ $fmt($declaration->rend_trib_pj) }}</span>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
                        <span>Recebimentos PF/Exterior (Rendimentos - Deducoes - Carne-leao)</span>
                        <span class="whitespace-nowrap text-right font-medium tabular-nums text-slate-800">{{ $fmt($declaration->rend_trib_pf_exterior) }}</span>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Renda isenta</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($rendaIsenta) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Rend. Trib. Exclusiva/Definitiva</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_rend_exclusiva) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Rendimentos recebidos acumuladamente</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_rend_recebidos_acumuladamente) }}</div>
                <div class="mt-1 text-xs text-slate-500">Calculo: Rendimentos - IRRF.</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Renda variavel (operacoes comuns / day trade)</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_renda_variavel) }}</div>
                <div class="mt-1 text-xs text-slate-500">Calculo pela base de calculo do imposto. Jan-Nov aplica aliquotas vigentes por coluna; dezembro considera somente as bases.</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Pagamentos efetuados</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_pagamentos_efetuados) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Doacoes efetuadas</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_doacoes_efetuadas) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Doacoes a partidos politicos</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_doacoes_partidos_politicos) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Atividade rural (resultado tributavel)</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_atividade_rural_resultado_tributavel) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Dividas e onus reais</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_dividas_onus_reais) }}</div>
                <div class="mt-2 space-y-1 text-xs text-slate-500">
                    <div>{{ $declaration->ano_base }}: {{ $fmt($declaration->total_dividas_ano_atual) }}</div>
                    <div>{{ $declaration->ano_base - 1 }}: {{ $fmt($declaration->total_dividas_ano_anterior) }}</div>
                    <div>Formula: {{ $declaration->ano_base }} - {{ $declaration->ano_base - 1 }}</div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Gastos estimados</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($gastosEstimados) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Bens</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($declaration->total_bens_reais) }}</div>
                <div class="mt-2 space-y-1 text-xs text-slate-500">
                    <div>{{ $declaration->ano_base }}: {{ $fmt($declaration->total_bens_ano_atual) }}</div>
                    <div>{{ $declaration->ano_base - 1 }}: {{ $fmt($declaration->total_bens_ano_anterior) }}</div>
                    <div>Formula: {{ $declaration->ano_base }} - {{ $declaration->ano_base - 1 }}</div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Gastos declarados</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($gastosDeclarados) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">Regra aplicada</div>
            <p class="mt-1 text-sm text-slate-700">Risco quando Caixa &lt; 20% da Evolucao Patrimonial.</p>
            <div class="mt-3 text-sm text-slate-800 space-y-1">
                <div>Entradas e Saidas: {{ $fmt($entradasSaidas) }}</div>
                <div>Evolucao Patrimonial: {{ $fmt($evolucaoPatrimonial) }}</div>
                <div>Caixa: {{ $fmt($caixaTotal) }}</div>
                <div>Limite de risco (20%): {{ $fmt($limiteRisco) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">Diagnostico</div>
            @if ($risco)
                <p class="mt-2 text-sm text-red-700">Ha indicio de risco pela regra atual de Caixa.</p>
            @else
                <p class="mt-2 text-sm text-emerald-700">Sem indicio de risco pela regra atual.</p>
            @endif
        </div>

        @if ($topIsentos->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm font-semibold text-slate-900">Rendimentos isentos (detalhe)</div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-800">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Codigo</th>
                                <th class="px-3 py-2">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topIsentos as $isento)
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 font-medium text-slate-700">{{ $isento->cod_isento }}</td>
                                    <td class="px-3 py-2">{{ $fmt($isento->valor) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
