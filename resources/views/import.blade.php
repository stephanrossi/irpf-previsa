@extends('layouts.app')

@section('title', 'Importar .DEC')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Importar declaração (.DEC)</h1>
                <p class="text-sm text-slate-600">O arquivo é armazenado em storage privado. CPF é tratado de forma sigilosa.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← Voltar</a>
        </div>

        <form action="{{ route('import.store') }}" method="POST" enctype="multipart/form-data"
              class="space-y-4 rounded-xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            @csrf

            <div class="space-y-2">
                <label for="file" class="text-sm font-medium text-slate-800">Arquivo .DEC</label>
                <input id="file" name="file" type="file" accept=".dec,text/plain"
                       class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <p class="text-xs text-slate-500">Somente arquivos .DEC, máximo 5MB.</p>
                @error('file')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800">
                Importar
            </button>
        </form>
    </div>
@endsection
