@extends('layouts.app')

@section('title', 'IRPF - Clientes')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Clientes</h1>
                <p class="text-sm text-slate-600">Visualize clientes e detalhes de declarações.</p>
            </div>
            <a href="{{ route('import.create') }}"
               class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                + Novo
            </a>
        </div>

        <form method="GET" action="{{ route('clients.index') }}" class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <label class="block text-sm font-medium text-slate-700">Buscar por nome ou CPF</label>
            <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-start">
                <div class="w-full lg:flex-1">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center">
                        <input type="text" name="q" value="{{ $search }}"
                               class="w-full md:flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                               placeholder="Digite o nome ou CPF">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center">
                            <div class="flex items-center gap-2 md:w-[250px]">
                                <label for="complexity" class="text-sm font-medium text-slate-700 whitespace-nowrap">Complexidade:</label>
                                <select id="complexity" name="complexity"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                    <option value="">Todas</option>
                                    <option value="baixa" {{ $complexity === 'baixa' ? 'selected' : '' }}>Baixa</option>
                                    <option value="media" {{ $complexity === 'media' ? 'selected' : '' }}>M&eacute;dia</option>
                                    <option value="alta" {{ $complexity === 'alta' ? 'selected' : '' }}>Alta</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2 md:w-[230px]">
                                <label for="ano_base" class="text-sm font-medium text-slate-700 whitespace-nowrap">Ano-base:</label>
                                <select id="ano_base" name="ano_base"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                    <option value="">Todos</option>
                                    @foreach ($anoBaseOptions as $year)
                                        <option value="{{ $year }}" {{ $anoBase === $year ? 'selected' : '' }}>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-2 text-sm text-slate-700 lg:min-w-[260px]">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="risk_only" value="1" {{ $riskOnly ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300 cursor-pointer">
                        <span>Somente em risco</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="retificadora_only" value="1" {{ $retificadoraOnly ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300 cursor-pointer">
                        <span>Somente retificadoras</span>
                    </label>
                </div>
                <div class="flex flex-col gap-2 lg:min-w-[220px]">
                    <div class="flex gap-2">
                    <button type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 cursor-pointer">
                        Buscar
                    </button>
                    <a href="{{ route('clients.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white/80 shadow-sm">
            <div class="hidden bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 md:grid md:grid-cols-[2fr_1fr_1fr_1fr_80px]">
                <a href="{{ route('clients.index', array_merge(request()->all(), ['sort' => 'nome', 'direction' => $sort === 'nome' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                   class="flex items-center gap-1 cursor-pointer">
                    Cliente <span class="text-slate-400">{{ $sort === 'nome' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                </a>
                <a href="{{ route('clients.index', array_merge(request()->all(), ['sort' => 'cpf', 'direction' => $sort === 'cpf' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                   class="flex items-center gap-1 cursor-pointer">
                    CPF <span class="text-slate-400">{{ $sort === 'cpf' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                </a>
                <a href="{{ route('clients.index', array_merge(request()->all(), ['sort' => 'status', 'direction' => $sort === 'status' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                   class="flex items-center gap-1 cursor-pointer">
                    Status <span class="text-slate-400">{{ $sort === 'status' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                </a>
                <a href="{{ route('clients.index', array_merge(request()->all(), ['sort' => 'anos', 'direction' => $sort === 'anos' && $direction === 'asc' ? 'desc' : 'asc'])) }}"
                   class="flex items-center gap-1 cursor-pointer">
                    NÚM. DECLARAÇÕES <span class="text-slate-400">{{ $sort === 'anos' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                </a>
                <form method="GET" action="{{ route('clients.index') }}" class="flex items-center justify-end gap-1">
                    @foreach (request()->except(['per_page', 'page']) as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <span class="normal-case tracking-normal text-slate-600">Qde:</span>
                    <select name="per_page"
                            class="rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium normal-case tracking-normal text-slate-700 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 cursor-pointer"
                            onchange="this.form.submit()">
                        <option value="20" {{ $perPage === 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ $perPage === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </form>
            </div>

            @forelse ($clients as $client)
                <div class="border-t border-slate-100 first:border-t-0">
                    <a href="{{ route('clients.show', array_merge(['client' => $client], request()->query())) }}" class="block transition hover:bg-slate-100/70">
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

