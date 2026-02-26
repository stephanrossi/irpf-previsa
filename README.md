# IRPF Previsa

Aplicacao Laravel para importar declaracoes IRPF em formato `.DEC`, organizar dados por cliente/ano-base e acompanhar indicadores em um painel web.

## Funcionalidades atuais

- Importacao de um ou varios arquivos `.DEC` na mesma operacao.
- Validacao de arquivos no upload (`.DEC` e limite de tamanho por arquivo).
- Barra de progresso e feedback de erros durante a importacao.
- Cadastro/atualizacao automatica de cliente por CPF.
- Consolidacao de declaracoes por cliente e ano-base.
- Bloqueio de importacao duplicada do mesmo arquivo.
- Regras para retificadora (aceita retificadora para atualizar ano ja existente e bloqueia original duplicada).
- Registro de historico de importacoes com metadados da importacao.
- Armazenamento dos arquivos importados em storage local privado.
- Dashboard de clientes com:
  - busca por nome ou CPF;
  - filtro "somente em risco";
  - filtro "somente retificadoras";
  - ordenacao por cliente, CPF, status e quantidade de declaracoes;
  - paginacao com selecao de itens por pagina.
- Tela do cliente com abas por ano-base.
- Exibicao de indicadores financeiros por declaracao (rendas, bens, dividas, gastos e relacionados).
- Edicao de gastos estimados com salvamento via AJAX e atualizacao imediata do status.
- Exibicao de gastos declarados com tabela por categoria e grafico (pizza/doughnut).
- Graficos de evolucao por ano-base (renda tributavel, renda isenta, bens e dividas).
- Relatorio detalhado por declaracao em rota dedicada (`/declarations/{declaration}/report`).
- Registro de acesso web (metadados de requisicao) em log da aplicacao.

## Stack

- Laravel 12
- PHP 8.3
- Tailwind CSS
- Alpine.js
- Chart.js
- PHPUnit/Pest

## Como rodar

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev   # ou npm run build
php artisan serve
```

## Testes

```bash
php artisan test
```
