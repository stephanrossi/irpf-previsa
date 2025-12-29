@extends('layouts.app')

@section('title', 'IRPF - Clientes')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Clientes</h1>
                <p class="text-sm text-slate-600">Visualize clientes e anos já importados.</p>
            </div>
            <a href="{{ route('import.create') }}"
               class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                + Novo
            </a>
        </div>

        <form method="GET" action="{{ route('clients.index') }}" class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <label class="block text-sm font-medium text-slate-700">Buscar por nome ou CPF</label>
            <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-center">
                <input type="text" name="q" value="{{ $search }}"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                       placeholder="Digite o nome ou CPF">
                <div class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="risk_only" value="1" {{ $riskOnly ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300">
                    <span>Somente em risco</span>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                        Buscar
                    </button>
                    <a href="{{ route('clients.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white/80 shadow-sm">
            <div class="hidden bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 md:grid md:grid-cols-[2fr_1fr_1fr_1fr_80px]">
                <span>Cliente</span>
                <span>CPF</span>
                <span>Status</span>
                <span>Anos importados</span>
                <span></span>
            </div>

            @forelse ($clients as $client)
                <div class="border-t border-slate-100 first:border-t-0">
                    <a href="{{ route('clients.show', $client) }}" class="block transition hover:bg-slate-50/70">
                        <div class="grid grid-cols-1 gap-2 px-4 py-4 text-sm text-slate-800 md:grid-cols-[2fr_1fr_1fr_1fr_80px] md:items-center">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $client->nome }}</div>
                            </div>
                            <div class="text-slate-700">{{ $client->formatted_cpf }}</div>
                            <div>
                                @if ($client->risk_declarations_count > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">
                                        • Em risco
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        • OK
                                    </span>
                                @endif
                            </div>
                            <div class="text-slate-700">{{ $client->declarations_count }}</div>
                            <div class="text-right text-xs font-medium text-slate-500">Abrir →</div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-slate-600">
                    Nenhum cliente importado ainda. Faça o primeiro upload.
                </div>
            @endforelse
        </div>

        <div>
            {{ $clients->links() }}
        </div>
    </div>
@endsection
