@extends('layouts.app')

@section('title', 'Relatório de Inconsistência')

@section('content')
    @php
        $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $rendaTrib = $payload['renda_tributavel_total'] ?? (float) $declaration->total_rend_tributaveis;
        $rendaIsenta = $payload['total_renda_isenta'] ?? (float) $declaration->total_renda_isenta;
        $gastosEstimados = $payload['gastos_estimados'] ?? ($declaration->gastos_estimados ?? 0);
        $gastosDeclarados = $payload['gastos_declarados_total'] ?? (float) $declaration->gastos_declarados_total;
        $gastosTotal = $gastosEstimados + $gastosDeclarados;
        $bensAno = $payload['total_bens_adquiridos_ano'] ?? (float) $declaration->total_bens_adquiridos_ano;
        $variacao = $payload['variacao_patrimonial_descoberto'] ?? (float) $declaration->variacao_patrimonial_descoberto;
        $status = $payload['status'] ?? ($declaration->risco_variacao_patrimonial ? 'RISCO' : 'OK');
        $risco = strtoupper($status) === 'RISCO';
    @endphp

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-600">Cliente</div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $declaration->client->nome }}</h1>
                <div class="text-sm text-slate-600">CPF: {{ $declaration->client->masked_cpf }}</div>
                <div class="text-sm text-slate-600">Ano-base {{ $declaration->ano_base }} · Exercício {{ $declaration->exercicio }}</div>
            </div>
            <div class="flex items-center gap-3">
                @if ($risco)
                    <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                        EM RISCO — Variação a descoberto: {{ $fmt($variacao) }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                        OK — Sem indício pela regra
                    </span>
                @endif
                <a href="{{ route('clients.show', $declaration->client) }}" class="text-sm text-slate-600 hover:text-slate-900">← Voltar</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Renda Tributável</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($rendaTrib) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Renda Isenta</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($rendaIsenta) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Gastos Estimados</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($gastosEstimados) }}</div>
                @if ($declaration->gastos_estimados === null)
                    <div class="mt-1 text-xs text-amber-700">Gastos estimados não informados; risco pode estar superestimado.</div>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Bens adquiridos no ano</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($bensAno) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm text-slate-600">Gastos declarados</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $fmt($gastosDeclarados) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">Regra aplicada</div>
            <p class="mt-1 text-sm text-slate-700">Bens Adquiridos &gt; (Renda Tributável + Renda Isenta - (Gastos Estimados + Gastos Declarados))</p>
            <div class="mt-3 text-sm text-slate-800">
                <div>Bens Adquiridos: {{ $fmt($bensAno) }}</div>
                <div>Renda Tributável + Renda Isenta - (Gastos Estimados + Declarados): {{ $fmt($rendaTrib + $rendaIsenta - $gastosTotal) }}</div>
                <div class="mt-2 font-semibold">Resultado: {{ $fmt($variacao) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">Diagnóstico</div>
            @if ($risco)
                <p class="mt-2 text-sm text-red-700">Há indício de variação patrimonial a descoberto.</p>
                <p class="text-sm text-slate-700">Valor estimado a descoberto: {{ $fmt($variacao) }}</p>
                <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-700">
                    <li>Revisar bens adquiridos/financiamentos.</li>
                    <li>Validar rendimentos isentos (doações, heranças, indenizações etc.).</li>
                    <li>Confirmar rendimentos tributáveis omitidos (PF/PJ/exterior).</li>
                    <li>Revisar ganhos de capital e atualizações.</li>
                </ol>
            @else
                <p class="mt-2 text-sm text-emerald-700">Sem indício de variação patrimonial a descoberto pela regra aplicada.</p>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">Aviso de valor</div>
            <p class="mt-2 text-sm text-slate-700">
                Aviso de risco vale mais do que só entregar no prazo: pode indicar variação patrimonial a descoberto.
            </p>
        </div>

        @if ($topIsentos->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                <div class="text-sm font-semibold text-slate-900">Rendimentos isentos (detalhe)</div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-800">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Código</th>
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
