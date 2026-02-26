@extends('layouts.app')

@section('title', $client->nome)

@section('content')
    @php
        $formatMoney = fn($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
        $buildCaixaMetrics = function ($declaration): array {
            $gastosEstimados = $declaration->gastos_estimados !== null ? (float) $declaration->gastos_estimados : 0.0;
            $entradasSaidas = (
                (float) $declaration->rend_trib_pj
                + (float) $declaration->rend_trib_pf_exterior
                + (float) $declaration->total_renda_isenta
                + (float) $declaration->total_rend_exclusiva
                + (float) $declaration->total_rend_recebidos_acumuladamente
                + (float) $declaration->total_renda_variavel
                + (float) $declaration->total_bens_reais
                + (float) $declaration->total_atividade_rural_resultado_tributavel
                - (float) $declaration->total_pagamentos_efetuados
                - (float) $declaration->total_doacoes_efetuadas
                - (float) $declaration->total_doacoes_partidos_politicos
                - (float) $declaration->total_dividas_onus_reais
                - $gastosEstimados
            );

            $evolucaoPatrimonial = (float) $declaration->total_bens_reais;
            $caixaTotal = $entradasSaidas - $evolucaoPatrimonial;
            $riscoCaixa = $evolucaoPatrimonial > 0 && $caixaTotal < ($evolucaoPatrimonial * 0.2);

            return [
                'gastos_estimados' => $gastosEstimados,
                'entradas_saidas' => $entradasSaidas,
                'evolucao_patrimonial' => $evolucaoPatrimonial,
                'caixa_total' => $caixaTotal,
                'risco_caixa' => $riscoCaixa,
            ];
        };
        $timeline = $declarations->sortBy('ano_base')->values();
        $chartPayload = [
            'years' => $timeline->pluck('ano_base')->values(),
            'rendimentos' => $timeline->map(fn ($d) => (float) $d->rend_trib_pj + (float) $d->rend_trib_pf_exterior)->values(),
            'renda_isenta' => $timeline->pluck('total_renda_isenta')->map(fn ($v) => (float) $v)->values(),
            'bens' => $timeline->pluck('total_bens_reais')->map(fn ($v) => (float) $v)->values(),
            'dividas' => $timeline->pluck('total_dividas_onus_reais')->map(fn ($v) => (float) $v)->values(),
        ];
    @endphp

    <div class="space-y-6" x-data="{ tab: null }">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm text-slate-600">Cliente</div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    <button type="button" @click="tab = null" class="underline-offset-4 hover:underline cursor-pointer">{{ $client->nome }}</button>
                </h1>
                <div class="text-sm text-slate-600">
                    CPF: <button type="button" @click="tab = null" class="underline-offset-4 hover:underline cursor-pointer">{{ $client->formatted_cpf }}</button>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('clients.index', request()->query()) }}" class="text-sm text-slate-600 hover:text-slate-900">&larr; Voltar</a>
                <a href="{{ route('import.create') }}"
                   class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                    Importar novo .DEC
                </a>
            </div>
        </div>

        @if ($declarations->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white/80 p-6 text-sm text-slate-700 shadow-sm">
                Nenhuma declara&ccedil;&atilde;o importada para este cliente ainda.
            </div>
        @else
            <div class="space-y-6">
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($declarations as $declaration)
                            @php
                                $caixaMetrics = $buildCaixaMetrics($declaration);
                                $limiteRisco = (float) $caixaMetrics['evolucao_patrimonial'] * 0.2;
                            @endphp
                            <button
                                type="button"
                                @click="tab = '{{ $declaration->ano_base }}'"
                                :class="tab === '{{ $declaration->ano_base }}'
                                    ? 'bg-slate-900 text-white shadow-sm hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300'
                                    : 'bg-white text-slate-800 border border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
                                class="rounded-full px-4 py-2 text-sm font-medium transition cursor-pointer">
                                <span class="inline-flex items-center gap-2">
                                    IR {{ $declaration->exercicio }} - Ano base {{ $declaration->ano_base }}
                                    @if ($caixaMetrics['risco_caixa'])
                                        <span class="relative inline-flex" x-data="{ showRiskCalc: false }" @mouseenter="showRiskCalc = true" @mouseleave="showRiskCalc = false">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                                                &bull; Risco
                                            </span>
                                            <div x-show="showRiskCalc" x-cloak class="pointer-events-none absolute left-1/2 top-full z-30 mt-2 w-72 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-3 text-left text-[11px] text-slate-700 shadow-lg">
                                                <div class="font-semibold text-slate-800">C&aacute;lculo do risco</div>
                                                <div class="mt-1">Caixa: {{ $formatMoney($caixaMetrics['caixa_total']) }}</div>
                                                <div>20% Evolu&ccedil;&atilde;o Patrimonial: {{ $formatMoney($limiteRisco) }}</div>
                                                <div class="mt-1">Regra: Caixa &lt; 20% da Evolu&ccedil;&atilde;o Patrimonial.</div>
                                            </div>
                                        </span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <div x-show="!tab" class="rounded-xl border border-dashed border-slate-200 bg-white/60 p-4 text-sm text-slate-600">
                        Selecione um ano-base para visualizar os totais.
                    </div>

                    @foreach ($declarations as $declaration)
                        @php
                            $caixaMetrics = $buildCaixaMetrics($declaration);
                            $totalRendTributaveis = (float) $declaration->rend_trib_pj + (float) $declaration->rend_trib_pf_exterior;
                            $complexityBreakdown = is_array($declaration->complexity_breakdown) ? $declaration->complexity_breakdown : [];
                        @endphp
                        <div x-data="{ showComplexityModal: false }" x-show="tab === '{{ $declaration->ano_base }}'" class="space-y-4" x-cloak>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                    {{ ucfirst($declaration->tipo) }}
                                </span>
                                @php
                                    $complexityMeta = match ($declaration->complexity_level) {
                                        'alta' => ['label' => 'Complexidade Alta', 'class' => 'bg-red-50 text-red-700'],
                                        'media' => ['label' => 'Complexidade Media', 'class' => 'bg-amber-50 text-amber-700'],
                                        'baixa' => ['label' => 'Complexidade Baixa', 'class' => 'bg-emerald-50 text-emerald-700'],
                                        default => ['label' => 'Complexidade Nao classificada', 'class' => 'bg-slate-100 text-slate-700'],
                                    };
                                @endphp
                                <button type="button"
                                        @click="showComplexityModal = true"
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $complexityMeta['class'] }} cursor-pointer">
                                    {{ $complexityMeta['label'] }}
                                </button>
                                <span class="text-slate-400">&bull;</span>
                                <span>Importado em {{ $declaration->imported_at?->format('d/m/Y H:i') }}</span>
                                @if ($declaration->last_is_retificadora)
                                    <span class="text-slate-400">&bull;</span>
                                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        Retificadora
                                        @if ($declaration->last_recibo_anterior)
                                            <span class="text-indigo-600">Recibo anterior: {{ $declaration->last_recibo_anterior }}</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <div x-show="showComplexityModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4" @click.self="showComplexityModal = false" @keydown.escape.window="showComplexityModal = false">
                                <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-base font-semibold text-slate-900">Classificacao de complexidade</h3>
                                        <button type="button" @click="showComplexityModal = false" class="cursor-pointer text-sm text-slate-500 hover:text-slate-700">Fechar</button>
                                    </div>
                                    <div class="mt-3 space-y-2 text-sm text-slate-700">
                                        <div>Pontuacao total = soma dos itens de volume (1 ponto por ocorrencia) + itens de alta complexidade.</div>
                                        <div>Alta complexidade: Renda variavel = +20 pontos; Atividade rural = +10 pontos.</div>
                                        <div>Faixas: Baixa (0 a 9), Media (10 a 29), Alta (30+).</div>
                                        <div class="pt-1 font-semibold text-slate-900">
                                            Resultado desta declaracao: {{ ucfirst($declaration->complexity_level ?? 'nao classificada') }} ({{ (int) ($declaration->complexity_score ?? 0) }} pontos)
                                        </div>
                                    </div>
                                    <div class="mt-4 border-t border-slate-100/70 pt-4">
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Criterios desta declaracao</div>
                                        @if (empty($complexityBreakdown))
                                            <div class="text-sm text-slate-600">Detalhamento indisponivel para esta declaracao.</div>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-sm text-slate-800">
                                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left">Criterio</th>
                                                            <th class="px-3 py-2 text-right">Base</th>
                                                            <th class="px-3 py-2 text-right">Multiplicador</th>
                                                            <th class="px-3 py-2 text-right">Pontos</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($complexityBreakdown as $criterion)
                                                            <tr class="border-t border-slate-100">
                                                                <td class="px-3 py-2">{{ $criterion['label'] ?? '-' }}</td>
                                                                <td class="px-3 py-2 text-right tabular-nums">{{ (int) ($criterion['base'] ?? 0) }}</td>
                                                                <td class="px-3 py-2 text-right tabular-nums">x{{ (int) ($criterion['multiplier'] ?? 1) }}</td>
                                                                <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ (int) ($criterion['points'] ?? 0) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-300 bg-white p-5 shadow-sm" x-data="{ showCaixaFormula: false }" @keydown.escape.window="showCaixaFormula = false">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="text-sm text-slate-600">Caixa</div>
                                    <button type="button"
                                            @click="showCaixaFormula = true"
                                            class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">
                                        Ver c&aacute;lculo
                                    </button>
                                </div>
                                <div id="caixa-value-{{ $declaration->id }}" class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $formatMoney($caixaMetrics['caixa_total']) }}</div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <div>Entradas e Sa&iacute;das: {{ $formatMoney($caixaMetrics['entradas_saidas']) }}</div>
                                    <div aria-hidden="true">-</div>
                                    <div>Evolu&ccedil;&atilde;o Patrimonial: {{ $formatMoney($caixaMetrics['evolucao_patrimonial']) }}</div>
                                </div>
                                <div x-show="showCaixaFormula" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4" @click.self="showCaixaFormula = false">
                                    <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-base font-semibold text-slate-900">C&aacute;lculo do Caixa</h3>
                                            <button type="button" @click="showCaixaFormula = false" class="cursor-pointer text-sm text-slate-500 hover:text-slate-700">Fechar</button>
                                        </div>
                                        <div class="mt-3 text-sm text-slate-700">
                                            Entradas e Sa&iacute;das = PJ + PF/Exterior + Isentos + Exclusiva + Acumulados + Renda Vari&aacute;vel + Evolu&ccedil;&atilde;o Patrimonial + Atividade Rural - Pagamentos - Doa&ccedil;&otilde;es - Doa&ccedil;&otilde;es a Partidos - D&iacute;vidas - Gastos Estimados.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Rendimentos tribut&aacute;veis</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($totalRendTributaveis) }}</div>
                                    <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-sm text-slate-600">
                                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
                                            <span>Recebimentos PJ (Rend PJ - Prev Oficial - IR Fonte + 13o - IR 13o)</span>
                                            <span class="whitespace-nowrap text-right font-medium tabular-nums text-slate-800">{{ $formatMoney($declaration->rend_trib_pj) }}</span>
                                        </div>
                                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
                                            <span>Recebimentos PF/Exterior (Rendimentos - Deducoes - Carne-leao)</span>
                                            <span class="whitespace-nowrap text-right font-medium tabular-nums text-slate-800">{{ $formatMoney($declaration->rend_trib_pf_exterior) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Renda isenta</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_renda_isenta) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Rend. Trib. Exclusiva/Definitiva</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_rend_exclusiva) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Rendimentos recebidos acumuladamente</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_rend_recebidos_acumuladamente) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">C&aacute;lculo: Rendimentos - IRRF.</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Renda vari&aacute;vel (opera&ccedil;&otilde;es comuns / day trade)</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_renda_variavel) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Jan-Nov: base comum - 15% e base day trade - 20%. Dezembro: somente bases.</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Pagamentos efetuados</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_pagamentos_efetuados) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Soma dos valores pagos informados na ficha de pagamentos efetuados.</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Doa&ccedil;&otilde;es efetuadas</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_doacoes_efetuadas) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Soma dos valores pagos informados na ficha de doa&ccedil;&otilde;es efetuadas.</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Doa&ccedil;&otilde;es a partidos pol&iacute;ticos</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_doacoes_partidos_politicos) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Soma da ficha de doa&ccedil;&otilde;es a partidos pol&iacute;ticos e candidatos.</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Atividade rural (resultado tribut&aacute;vel)</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_atividade_rural_resultado_tributavel) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Bens</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_bens_reais) }}</div>
                                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                                        <div>{{ $declaration->ano_base }}: {{ $formatMoney($declaration->total_bens_ano_atual) }}</div>
                                        <div>{{ $declaration->ano_base - 1 }}: {{ $formatMoney($declaration->total_bens_ano_anterior) }}</div>
                                        <div>F&oacute;rmula: {{ $declaration->ano_base }} - {{ $declaration->ano_base - 1 }}</div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">D&iacute;vidas e &ocirc;nus reais</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_dividas_onus_reais) }}</div>
                                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                                        <div>{{ $declaration->ano_base }}: {{ $formatMoney($declaration->total_dividas_ano_atual) }}</div>
                                        <div>{{ $declaration->ano_base - 1 }}: {{ $formatMoney($declaration->total_dividas_ano_anterior) }}</div>
                                        <div>F&oacute;rmula: {{ $declaration->ano_base }} - {{ $declaration->ano_base - 1 }}</div>
                                    </div>
                                </div>
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm" x-data="gastosForm({{ $declaration->id }}, {{ $declaration->gastos_estimados !== null ? (float) $declaration->gastos_estimados : 'null' }}, '{{ route('declarations.update-gastos', $declaration) }}', {{ (float) ($caixaMetrics['caixa_total'] + $caixaMetrics['gastos_estimados']) }})">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-slate-600">Gastos estimados</div>
                                        <button type="button"
                                                @click="saveNow()"
                                                :disabled="saving"
                                                :class="saving ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
                                                class="text-xs font-medium text-slate-800 underline underline-offset-2">
                                            Recalcular
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-500">R$</span>
                                            <input type="text"
                                                   inputmode="numeric"
                                                   :value="displayValue"
                                                   placeholder="0,00"
                                                   @input="applyMask($event)"
                                                   class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-12 pr-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs text-slate-600" x-text="statusText"></div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm md:col-span-2 lg:col-span-3" x-data="gastosDeclaradosChart({{ $declaration->id }}, @js($declaration->gastos_declarados_breakdown ?? []), {{ (float) $declaration->gastos_declarados_total }})" x-init="render()">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm text-slate-600">Gastos declarados</div>
                                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->gastos_declarados_total) }}</div>
                                    </div>
                                    <template x-if="hasZero">
                                        <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                            Algumas categorias est&atilde;o zeradas (sem dados)
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-5">
                                    <div class="md:col-span-3 overflow-x-auto">
                                        <table class="min-w-full text-left text-sm text-slate-800">
                                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                                                <tr>
                                                    <th class="px-3 py-2">Categoria</th>
                                                    <th class="px-3 py-2">Bruto</th>
                                                    <th class="px-3 py-2">Redu&ccedil;&atilde;o</th>
                                                    <th class="px-3 py-2">L&iacute;quido</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="row in tableRows" :key="row.key">
                                                    <tr class="border-t border-slate-100">
                                                        <td class="px-3 py-2 font-medium text-slate-700" x-text="row.label"></td>
                                                        <td class="px-3 py-2" x-text="row.bruto"></td>
                                                        <td class="px-3 py-2" x-text="row.reducao"></td>
                                                        <td class="px-3 py-2" x-text="row.liquido"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="md:col-span-2 flex items-center justify-center">
                                        <canvas :id="'pie-gastos-'+id" class="h-56 w-full"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div x-data="clientCharts()" x-init="renderCharts()" class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                    <div class="mb-3">
                        <div class="text-sm font-medium text-slate-800">Evolu&ccedil;&atilde;o dos valores</div>
                        <p class="text-xs text-slate-500">Linha do tempo por ano-base</p>
                    </div>
                    <template x-if="years.length === 0">
                        <div class="text-sm text-slate-600">Sem dados suficientes para gr&aacute;ficos.</div>
                    </template>
                    <div x-show="years.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Rendimentos tribut&aacute;veis</div>
                            <canvas id="chartRendimentos" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Renda isenta</div>
                            <canvas id="chartRendaIsenta" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Bens</div>
                            <canvas id="chartBens" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">D&iacute;vidas e &ocirc;nus reais</div>
                            <canvas id="chartDividas" class="h-full w-full"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($declarations->isNotEmpty())
        <script>
            function clientCharts() {
                const payload = @json($chartPayload);

                return {
                    years: payload.years || [],
                    renderCharts() {
                        if (!this.years.length || typeof Chart === 'undefined') return;

                        this.buildChart('chartRendimentos', 'Rendimentos tribut\u00E1veis', payload.rendimentos, '#0f172a');
                        this.buildChart('chartRendaIsenta', 'Renda isenta', payload.renda_isenta, '#0ea5e9');
                        this.buildChart('chartBens', 'Bens', payload.bens, '#111827');
                        this.buildChart('chartDividas', 'D\u00EDvidas e \u00F4nus reais', payload.dividas, '#dc2626');
                    },
                    buildChart(canvasId, label, data, color) {
                        const el = document.getElementById(canvasId);
                        if (!el) return;
                        new Chart(el.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: this.years,
                                datasets: [{
                                    label,
                                    data,
                                    borderColor: color,
                                    backgroundColor: color + '22',
                                    tension: 0.25,
                                    fill: true,
                                    pointRadius: 3,
                                    pointBackgroundColor: color,
                                }],
                            },
                            options: {
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { display: false } },
                                    y: { grid: { color: '#e5e7eb' } },
                                },
                                responsive: true,
                                maintainAspectRatio: true,
                            },
                        });
                    },
                };
            }
            function gastosForm(id, initial, url, caixaBaseSemGastos) {
                const toDigits = (value) => {
                    if (value === null || value === '' || typeof value === 'undefined') return '';
                    const cents = Math.round(Number(value) * 100);
                    if (!Number.isFinite(cents) || cents < 0) return '';
                    return String(cents);
                };

                const formatFromDigits = (digits) => {
                    if (!digits) return '';
                    const cents = Number(digits);
                    if (!Number.isFinite(cents)) return '';
                    return (cents / 100).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                };

                return {
                    rawDigits: toDigits(initial),
                    displayValue: formatFromDigits(toDigits(initial)),
                    statusText: initial !== null ? 'Salvo' : 'Informe para refinar o risco',
                    saving: false,
                    caixaBaseSemGastos: Number(caixaBaseSemGastos || 0),
                    applyMask(event) {
                        const digits = event.target.value.replace(/\D/g, '');
                        this.rawDigits = digits;
                        this.displayValue = formatFromDigits(digits);
                        this.statusText = 'Clique em Recalcular para aplicar no Caixa.';
                    },
                    saveNow() {
                        const parsed = this.rawDigits === '' ? null : Number(this.rawDigits) / 100;
                        this.saving = true;
                        fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ gastos_estimados: parsed }),
                        }).then(async res => {
                            if (!res.ok) {
                                throw await res.text();
                            }
                            return res.json();
                        }).then(data => {
                            const detalhesRisco = (data.caixa && data.limite)
                                ? ' \u2022 Caixa: R$ ' + data.caixa + ' \u2022 Limite (20%): R$ ' + data.limite
                                : '';
                            this.statusText = data.message + ' (' + data.status + detalhesRisco + ')';
                            const caixaEl = document.getElementById('caixa-value-' + id);
                            if (caixaEl) {
                                const novoCaixa = this.caixaBaseSemGastos - (parsed ?? 0);
                                caixaEl.textContent = formatCurrencyBRL(novoCaixa);
                            }
                        }).catch(() => {
                            this.statusText = 'Erro ao salvar. Tente novamente.';
                        }).finally(() => {
                            this.saving = false;
                        });
                    },
                };
            }
            function formatCurrencyBRL(value) {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
            }
            function gastosDeclaradosChart(id, breakdown, total) {
                const labels = {
                    planos_saude: 'Planos de Sa\u00FAde',
                    medicas_odont: 'M\u00E9dicas/Odonto',
                    instrucao: 'Instru\u00E7\u00E3o',
                    pensao_judicial: 'Pens\u00E3o judicial',
                    pgbl: 'PGBL',
                    ir_pago: 'IR pago',
                };
                const order = ['planos_saude','medicas_odont','instrucao','pensao_judicial','pgbl','ir_pago'];

                return {
                    id,
                    tableRows: order.map(key => {
                        const row = breakdown?.[key] ?? {};
                        return {
                            key,
                            label: labels[key],
                            bruto: formatCurrencyBRL(row.bruto ?? 0),
                            reducao: formatCurrencyBRL(row.reducao ?? 0),
                            liquido: formatCurrencyBRL(row.liquido ?? (key === 'ir_pago' ? row.liquido ?? 0 : 0)),
                            rawLiquido: row.liquido ?? 0,
                        };
                    }),
                    hasZero: false,
                    render() {
                        this.hasZero = this.tableRows.some(r => (r.rawLiquido ?? 0) === 0);
                        const dataSet = order.map(key => breakdown?.[key]?.liquido ?? 0);
                        const ctx = document.getElementById('pie-gastos-'+id);
                        if (!ctx || typeof Chart === 'undefined') return;
                        new Chart(ctx.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: order.map(k => labels[k]),
                                datasets: [{
                                    data: dataSet,
                                    backgroundColor: ['#0ea5e9','#0f172a','#6366f1','#dc2626','#f59e0b','#16a34a'],
                                }],
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => {
                                                const key = order[context.dataIndex];
                                                const row = breakdown?.[key] ?? {};
                                                return `${labels[key]}: ${formatCurrencyBRL(row.liquido ?? 0)} (bruto ${formatCurrencyBRL(row.bruto ?? 0)} / red. ${formatCurrencyBRL(row.reducao ?? 0)})`;
                                            },
                                        },
                                    },
                                    legend: { position: 'bottom' },
                                },
                            },
                        });
                    },
                };
            }
        </script>
    @endif
@endsection
