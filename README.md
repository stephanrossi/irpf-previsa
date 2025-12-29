# IRPF DEC Inspector

Aplicação Laravel 12 (PHP 8.3) para importar declarações IRPF (.DEC), organizar por cliente/ano e entregar um painel enxuto com totais, gastos declarados e alertas de risco patrimonial. Interface minimalista com Tailwind + Alpine e gráficos Chart.js.

## Visão geral
- **Importação .DEC** (storage privado) com parser streaming (SplFileObject).
- **Dados-chave por ano**: renda tributável, renda isenta, bens imóveis, dívidas, bens adquiridos no ano.
- **Gastos declarados**: planos de saúde, médicas/odonto, instrução, pensão judicial, PGBL e IR pago (registros 26 e 20), com memória de cálculo detalhada.
- **Risco patrimonial**: regra simples — bens adquiridos > (renda tributável + renda isenta - (gastos estimados + declarados)) sinaliza “EM RISCO”. Badge em cliente/ano e relatório dedicado.
- **UI limpa**: dashboard com busca + filtro “somente em risco”, abas por ano do cliente, cards de totais, input de gastos estimados com salvamento via AJAX, gráficos de evolução e pizza para gastos declarados, relatório explicativo por ano.

## Stack
- Laravel 12, PHP 8.3
- Tailwind 4 + Alpine.js + Chart.js
- Pest/PHPUnit para testes

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

## Fluxo principal
1) Acesse `/import`, envie o .DEC (validação de extensão/mime/tamanho).  
2) O parser extrai header, totais (reg 20/18), bens (reg 27), dívidas (reg 28), renda isenta (reg 20/18 + reg 23), gastos declarados (reg 26) e IR pago (reg 20).  
3) Cria/atualiza cliente e ano; salva arquivo em storage local (privado).  
4) Calcula risco com `IrpfInconsistencyService` e grava payload.  
5) Na página do cliente: escolha o ano, veja cards, edite “Gastos estimados” (AJAX) e consulte o “Relatório de Inconsistência”.  

## Telas
- **Dashboard**: busca por nome/CPF, filtro “somente em risco”, badge de risco por cliente.
- **Cliente**: abas por ano, cards de renda, bens, dívidas, renda isenta, bens adquiridos, gastos estimados + recalcular, card “Gastos declarados” (tabela + gráfico de pizza), gráficos de evolução por ano.
- **Relatório**: fórmula aplicada com números, diagnóstico (OK/EM RISCO), valor a descoberto, checklist de ações e detalhe de renda isenta.

## Testes
```bash
php artisan test
```

## Privacidade
- CPF mascarado na UI (`***.***.***-xx`).
- Arquivos .DEC armazenados apenas em `storage/app/declarations`.
- Conteúdo do arquivo não é logado; só metadados/totais são persistidos.
