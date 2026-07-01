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

Projeto full stack funcional, com foco em controle operacional de uma loja de games.
