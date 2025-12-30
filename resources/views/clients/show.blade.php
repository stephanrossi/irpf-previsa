@extends('layouts.app')

@section('title', $client->nome)

@section('content')
    @php
        $formatMoney = fn($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
        $timeline = $declarations->sortBy('ano_base')->values();
        $chartPayload = [
            'years' => $timeline->pluck('ano_base')->values(),
            'rendimentos' => $timeline->pluck('total_rend_tributaveis')->map(fn ($v) => (float) $v)->values(),
            'renda_isenta' => $timeline->pluck('total_renda_isenta')->map(fn ($v) => (float) $v)->values(),
            'bens' => $timeline->pluck('total_bens_imoveis')->map(fn ($v) => (float) $v)->values(),
            'dividas' => $timeline->pluck('total_dividas_onus')->map(fn ($v) => (float) $v)->values(),
            'bens_ano' => $timeline->pluck('total_bens_adquiridos_ano')->map(fn ($v) => (float) $v)->values(),
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
                <a href="{{ route('clients.index', request()->query()) }}" class="text-sm text-slate-600 hover:text-slate-900">← Voltar</a>
                <a href="{{ route('import.create') }}"
                   class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                    Importar novo .DEC
                </a>
            </div>
        </div>

        @if ($declarations->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white/80 p-6 text-sm text-slate-700 shadow-sm">
                Nenhuma declaração importada para este cliente ainda.
            </div>
        @else
            <div class="space-y-6">
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($declarations as $declaration)
                            <button
                                type="button"
                                @click="tab = '{{ $declaration->ano_base }}'"
                                :class="tab === '{{ $declaration->ano_base }}'
                                    ? 'bg-slate-900 text-white shadow-sm hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300'
                                    : 'bg-white text-slate-800 border border-slate-200 hover:bg-slate-100 hover:border-slate-300'"
                                class="rounded-full px-4 py-2 text-sm font-medium transition cursor-pointer">
                                Ano-base {{ $declaration->ano_base }} · Exercício {{ $declaration->exercicio }}
                            </button>
                        @endforeach
                    </div>

                    <div x-show="!tab" class="rounded-xl border border-dashed border-slate-200 bg-white/60 p-4 text-sm text-slate-600">
                        Selecione um ano-base para visualizar os totais.
                    </div>

                    @foreach ($declarations as $declaration)
                        <div x-show="tab === '{{ $declaration->ano_base }}'" class="space-y-4" x-cloak>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                    {{ ucfirst($declaration->tipo) }}
                                </span>
                                <span class="text-slate-400">•</span>
                                <span>Importado em {{ $declaration->imported_at?->format('d/m/Y H:i') }}</span>
                                <span class="text-slate-400">•</span>
                                @if ($declaration->risco_variacao_patrimonial)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">
                                        EM RISCO
                                        <span class="text-red-600">Variação a descoberto: {{ $formatMoney($declaration->variacao_patrimonial_descoberto) }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        OK
                                    </span>
                                @endif
                                @if ($declaration->last_is_retificadora)
                                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        Retificadora
                                        @if ($declaration->last_recibo_anterior)
                                            <span class="text-indigo-600">Recibo anterior: {{ $declaration->last_recibo_anterior }}</span>
                                        @endif
                                    </span>
                                @endif
                                <a href="{{ route('declarations.report', $declaration) }}" class="text-xs font-medium text-slate-800 underline underline-offset-2">
                                    Ver Relatório de Inconsistência
                                </a>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Rendimentos tributáveis</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_rend_tributaveis) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Renda isenta</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_renda_isenta) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Bens imóveis</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_bens_imoveis) }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                    <div class="text-sm text-slate-600">Dívidas e ônus</div>
                                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_dividas_onus) }}</div>
                                </div>
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                                <div class="text-sm text-slate-600">Bens adquiridos no ano</div>
                                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->total_bens_adquiridos_ano) }}</div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm" x-data="gastosForm({{ $declaration->id }}, {{ $declaration->gastos_estimados !== null ? (float) $declaration->gastos_estimados : 'null' }}, '{{ route('declarations.update-gastos', $declaration) }}')">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-slate-600">Gastos estimados</div>
                                        <button type="button" @click="saveNow()" class="text-xs font-medium text-slate-800 underline underline-offset-2">Recalcular</button>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <input type="number" step="0.01" min="0" x-model="valor"
                                               @input="debouncedSave()"
                                               class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                    </div>
                                    <div class="mt-2 text-xs text-slate-600" x-text="statusText"></div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm md:col-span-2" x-data="gastosDeclaradosChart({{ $declaration->id }}, @js($declaration->gastos_declarados_breakdown ?? []), {{ (float) $declaration->gastos_declarados_total }})" x-init="render()">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm text-slate-600">Gastos declarados</div>
                                        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $formatMoney($declaration->gastos_declarados_total) }}</div>
                                    </div>
                                    <template x-if="hasZero">
                                        <div class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                            Algumas categorias estão zeradas (sem dados)
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
                                                    <th class="px-3 py-2">Redução</th>
                                                    <th class="px-3 py-2">Líquido</th>
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
                        <div class="text-sm font-medium text-slate-800">Evolução dos valores</div>
                        <p class="text-xs text-slate-500">Linha do tempo por ano-base</p>
                    </div>
                    <template x-if="years.length === 0">
                        <div class="text-sm text-slate-600">Sem dados suficientes para gráficos.</div>
                    </template>
                    <div x-show="years.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Rendimentos tributáveis</div>
                            <canvas id="chartRendimentos" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Renda isenta</div>
                            <canvas id="chartRendaIsenta" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Bens imóveis</div>
                            <canvas id="chartBens" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Dívidas e ônus</div>
                            <canvas id="chartDividas" class="h-full w-full"></canvas>
                        </div>
                        <div class="space-y-2 rounded-lg border border-slate-100 bg-white p-3 h-64">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Bens adquiridos no ano</div>
                            <canvas id="chartBensAno" class="h-full w-full"></canvas>
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

                        this.buildChart('chartRendimentos', 'Rendimentos tributáveis', payload.rendimentos, '#0f172a');
                        this.buildChart('chartRendaIsenta', 'Renda isenta', payload.renda_isenta, '#0ea5e9');
                        this.buildChart('chartBens', 'Bens imóveis', payload.bens, '#111827');
                        this.buildChart('chartDividas', 'Dívidas e ônus', payload.dividas, '#dc2626');
                        this.buildChart('chartBensAno', 'Bens adquiridos no ano', payload.bens_ano, '#2563eb');
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
            function gastosForm(id, initial, url) {
                return {
                    valor: initial,
                    statusText: initial !== null ? 'Salvo' : 'Informe para refinar o risco',
                    timeout: null,
                    saving: false,
                    debouncedSave() {
                        clearTimeout(this.timeout);
                        this.timeout = setTimeout(() => this.saveNow(), 800);
                    },
                    saveNow() {
                        const parsed = this.valor === null || this.valor === '' ? null : parseFloat(this.valor);
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
                            this.statusText = data.message + ' (' + data.status + (data.variacao ? ' • Variação: R$ ' + data.variacao : '') + ')';
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
                    planos_saude: 'Planos de Saúde',
                    medicas_odont: 'Médicas/Odonto',
                    instrucao: 'Instrução',
                    pensao_judicial: 'Pensão judicial',
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
