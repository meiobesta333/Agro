# Agro — versão PHP/MySQL para hospedagem compartilhada

O projeto foi direcionado para hospedagem tradicional com PHP e MySQL (como InfinityFree). Não depende de Node.js, Next.js, Supabase, serviços externos ou processo em segundo plano.

## Recursos iniciais
- Cadastro e login com senha armazenada usando `password_hash()`
- Sessões PHP e proteção CSRF nos formulários
- Cadastro de propriedades rurais
- Cadastro de talhões e cultura
- Diário de atividades por propriedade
- Consultas SQL preparadas com PDO
- Isolamento dos dados por proprietário
- Layout responsivo para celular e computador

## Requisitos
- PHP 8.1 ou superior (InfinityFree informa PHP 8.4 no hosting gratuito)
- MySQL 5.7+ ou MariaDB compatível
- Extensão PDO MySQL habilitada
- Apache com suporte a `.htaccess`

## Instalação no InfinityFree
1. Crie um banco MySQL no painel da hospedagem.
2. Copie `config.example.php` para `config.php`.
3. Edite `config.php` com host, nome do banco, usuário e senha fornecidos pelo painel. Não use localhost se o painel informar outro host.
4. Envie todos os arquivos e pastas para `htdocs` pelo Gerenciador de Arquivos ou FTP.
5. Acesse `https://SEU-DOMINIO/install.php` uma única vez para criar as tabelas. O instalador grava um marcador para impedir uma segunda instalação acidental.
6. Exclua `install.php` do servidor imediatamente após a instalação.
7. Acesse o site e crie a primeira conta em “Criar conta”.

## Segurança
- Nunca envie `config.php` ao GitHub; ele contém credenciais do banco.
- Não deixe `install.php` no servidor após a instalação.
- O MVP limita a visualização de propriedades ao usuário proprietário. Compartilhamento com profissionais será implementado em etapa posterior com convites e permissões explícitas.
- Use HTTPS e uma senha forte para a conta e para o banco.
- Este projeto é uma base de testes, ainda não uma aplicação pronta para produção.

## Estrutura
```
app/bootstrap.php
assets/app.css
config.example.php
index.php
install.php
```

## Próximas etapas
1. Testar a instalação no ambiente real do InfinityFree.
2. Implementar edição/exclusão, compartilhamento profissional-produtor e permissões.
3. Adicionar visitas técnicas, relatórios PDF e anexos.
4. Implementar backups/exportação e auditoria.
