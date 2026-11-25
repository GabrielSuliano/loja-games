# Loja Games

Sistema web de loja de games com frontend e backend em PHP. O projeto possui autenticacao de funcionarios, controle de estoque, clientes, vendas, relatorios e dashboard administrativo.

## Recursos

- Login de funcionarios.
- Controle de sessao PHP.
- Dashboard administrativo.
- Cadastro e gestao de produtos.
- Controle de estoque.
- Cadastro de clientes.
- Registro de vendas.
- Relatorios e graficos.
- Exportacao em PDF.
- Area de usuarios/admin.
- Tema com suporte a modo escuro via sessao.

## Stack

- PHP
- MySQL
- MySQLi
- HTML5
- CSS3
- JavaScript
- XAMPP para ambiente local

## Estrutura

```text
.
├── index.php
├── dashboard.php
├── produtos.php
├── estoque.php
├── clientes.php
├── vendas.php
├── relatorios.php
├── admin_usuarios.php
├── includes/
├── css/
├── js/
├── imagens/
└── banco/
```

## Como rodar no XAMPP

1. Copie o projeto para `C:\xampp\htdocs\loja-games`.
2. Inicie Apache e MySQL.
3. Abra `http://localhost/phpmyadmin`.
4. Importe o arquivo SQL da pasta `banco/`.
5. Confira as credenciais de banco em:

```text
includes/config.php
```

6. Acesse:

```text
http://localhost/loja-games/
```

## Configuracao padrao do banco

```text
Host: localhost
Banco: loja_games
Usuario: root
Senha: vazia
```

## Observacoes

- Algumas rotas exigem usuario autenticado.
- Paginas administrativas dependem do nivel do funcionario.
- O arquivo SQL do banco deve ser importado antes do primeiro acesso.

## Status

Implementação demonstrativa full stack, com foco em controle operacional de uma loja de games.

## Fluxo e dados

Páginas PHP processam formulários, consultam MySQLi e renderizam HTML; `includes/config.php` inicia sessão e configura conexão/permissões. `banco/loja_games (2).sql` relaciona clientes/funcionários com vendas e vendas/produtos por `venda_itens`. Não há API REST separada ou framework MVC.

A venda registra itens e reduz estoque. O caminho de finalização não envolve toda a operação em transação; concorrência/falhas intermediárias precisam de testes. Não há pagamento real integrado.

## Limitações verificadas

- `relatorios.php` depende de `fpdf/fpdf.php` para PDF no servidor, mas a biblioteca não está versionada. Código de exportação no navegador também existe, sem resolver essa dependência.
- `includes/config.php` usa nível administrativo numérico; `includes/auth.php` compara perfil textual. O comportamento depende do arquivo incluído pela página; revisar a duplicidade.
- Há consultas montadas como texto SQL, sem suíte que comprove proteção uniforme contra injeção/CSRF.
- O dump contém registros de clientes/funcionários e hashes de autenticação. A origem e o caráter fictício dos dados não foram comprovados; trate-os como potencialmente pessoais até revisão. Não utilizar as contas em produção nem reproduzir seus valores.

## Ambiente, testes e evolução

Requer PHP/MySQLi e MySQL/MariaDB local. A conexão está diretamente em `includes/config.php`, sem loader `.env`. Revise o dump antes de importar manualmente em banco isolado. Nenhum SQL, login ou venda foi executado nesta revisão; não há suíte automatizada ou deploy confirmado.

Prioridades: dependência PDF, verificação de perfil, proteção de mutações e atomicidade de vendas/estoque. Não há licença versionada. Código existente não equivale a prontidão para produção.
