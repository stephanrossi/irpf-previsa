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

        <form x-data="importForm()" x-on:submit.prevent="startLoading" action="{{ route('import.store') }}" method="POST" enctype="multipart/form-data"
              class="space-y-4 rounded-xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            @csrf

            <div class="space-y-3">
                <div class="flex items-center gap-4">
                    <span class="text-base font-semibold text-slate-900">Arquivos .DEC</span>
                    <label for="files" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm border border-slate-200 cursor-pointer hover:bg-slate-50">
                        Escolher arquivos
                    </label>
                </div>
                <input id="files" name="files[]" type="file" accept=".dec,text/plain" multiple class="sr-only">
                <p class="text-sm text-slate-600">Envie um ou mais arquivos .DEC (máx 5MB cada).</p>
                @error('files')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
                @error('files.*')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60 cursor-pointer disabled:cursor-not-allowed">
                <svg x-show="loading" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span x-text="loading ? 'Importando...' : 'Importar'"></span>
            </button>
            <p x-show="loading" class="text-xs text-slate-600">Importando arquivos .DEC, aguarde...</p>
            <div x-show="loading" class="mt-3 w-full rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-slate-900 transition-all" :style="`width:${progress}%`"></div>
            </div>
            <div x-show="loading" class="text-xs text-slate-600" x-text="progressLabel"></div>

            <div x-cloak x-show="showModal" class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 backdrop-blur pt-20">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="text-sm font-semibold text-slate-900 mb-2">Aviso</div>
                    <div class="text-sm text-slate-700 text-left" x-text="modalMessage"></div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="showModal=false"
                                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script>
        function importForm() {
            return {
                loading: false,
                progress: 0,
                progressLabel: '',
                showModal: false,
                modalMessage: '',
                showMessage(msg) {
                    this.modalMessage = msg;
                    this.showModal = true;
                },
                async startLoading() {
                    const input = document.getElementById('files');
                    if (!input || !input.files || input.files.length === 0) {
                        this.showMessage('Selecione pelo menos um arquivo .DEC.');
                        return;
                    }
                    this.loading = true;
                    this.progress = 0;
                    this.progressLabel = 'Enviando arquivos...';

                    const fd = new FormData();
                    fd.append('_token', window.csrfToken);
                    for (let i = 0; i < input.files.length; i++) {
                        fd.append('files[]', input.files[i]);
                    }

                    await this.uploadWithProgress(fd, input.files.length);
                },
                uploadWithProgress(fd) {
                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', "{{ route('import.store') }}", true);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.upload.onprogress = (e) => {
                            if (e.lengthComputable) {
                                this.progress = Math.round((e.loaded / e.total) * 100);
                                this.progressLabel = `Importando (${this.progress}%)`;
                            }
                        };
                        xhr.onerror = () => {
                            this.progressLabel = 'Erro ao importar. Verifique os arquivos.';
                            this.showMessage('Erro ao importar. Verifique os arquivos.');
                            this.loading = false;
                            reject();
                        };
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                const data = JSON.parse(xhr.response || '{}');
                                this.progress = 100;
                                this.progressLabel = 'Concluído';
                                window.location = data.client_url || "{{ route('clients.index') }}";
                                resolve();
                            } else {
                                this.progressLabel = 'Erro ao importar. Verifique os arquivos.';
                                this.showMessage('Erro ao importar. Verifique os arquivos.');
                                this.loading = false;
                                reject();
                            }
                        };
                        xhr.send(fd);
                    });
                },
            };
        }
    </script>
@endsection
