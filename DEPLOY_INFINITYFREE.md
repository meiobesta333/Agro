# Publicar o Agro no InfinityFree

Este guia prepara a versão PHP/MySQL atual para uma primeira instalação de testes.

## 1. Criar a hospedagem e o banco

1. Crie uma conta no InfinityFree e adicione um domínio ou subdomínio.
2. Abra o painel da conta e entre em **MySQL Databases**.
3. Crie um banco de dados.
4. Copie exatamente os valores de **MySQL Hostname**, **Database Name**, **Username** e **Password**. Não substitua o host por `localhost`.

A página oficial informa suporte a PHP 8.4, MySQL 8.0/MariaDB e SSL no hosting gratuito. A versão efetiva deve ser conferida no seu próprio espaço de hospedagem.

## 2. Baixar e enviar o projeto

1. Abra o repositório: https://github.com/meiobesta333/Agro
2. Clique em **Code → Download ZIP** e extraia o arquivo no computador.
3. Entre no gerenciador de arquivos do InfinityFree ou conecte por FTP.
4. Abra a pasta `htdocs`.
5. Envie o conteúdo do projeto para dentro de `htdocs`, mantendo as pastas `app` e `assets`.
6. O arquivo `index.php` deve ficar diretamente dentro de `htdocs`, e não dentro de uma subpasta adicional.

## 3. Configurar a conexão MySQL

1. No computador, faça uma cópia de `config.example.php` e chame a cópia de `config.php`.
2. Abra `config.php` e preencha os quatro valores com os dados do painel:

```php
<?php
return [
    'db_host' => 'HOST_MYSQL_DO_PAINEL',
    'db_name' => 'NOME_DO_BANCO',
    'db_user' => 'USUARIO_DO_BANCO',
    'db_pass' => 'SENHA_DO_BANCO',
];
```

3. Envie `config.php` para `htdocs`. O arquivo `.htaccess` bloqueia seu acesso direto pelo navegador.
4. **Não envie esse arquivo ao GitHub** e não compartilhe a senha do banco em mensagens.

## 4. Criar as tabelas

1. Abra `https://SEU-DOMINIO/install.php`.
2. Se aparecer “Instalação concluída”, as tabelas foram criadas.
3. Volte ao gerenciador de arquivos e exclua `install.php`.
4. Abra `https://SEU-DOMINIO/diagnostico.php` e confira se PHP, PDO MySQL, banco e instalação aparecem corretamente.
5. Exclua também `diagnostico.php` após a verificação.

O instalador cria um marcador no banco para impedir uma instalação repetida acidental.

## 5. Primeiro acesso e teste

1. Abra `https://SEU-DOMINIO/`.
2. Clique em **Criar conta** e cadastre seu usuário de teste.
3. Entre com o e-mail e a senha cadastrados.
4. Cadastre uma propriedade.
5. Abra a propriedade, cadastre um talhão e registre uma atividade.
6. Saia e entre novamente para conferir se os dados continuam salvos.

## 6. Se aparecer erro

- **Configuração incompleta:** confira se `config.php` existe em `htdocs` e se os quatro valores foram preenchidos.
- **Falha na conexão MySQL:** use o hostname exatamente como aparece no painel; não use `localhost` por padrão.
- **Erro 500:** confira o log de erros PHP no painel e confirme que o conteúdo foi enviado diretamente para `htdocs`.
- **Página em branco:** abra o diagnóstico; não deixe arquivos de diagnóstico ou instalador publicados após resolver o problema.

## Limites conhecidos desta versão

- O cadastro de profissional e produtor já existe, mas o compartilhamento entre contas ainda não foi implementado.
- Não há envio de fotos, relatórios PDF, notificações ou modo offline nesta etapa.
- A versão é para testes iniciais, não para dados sensíveis ou operação comercial em produção.
- Faça backup do banco antes de qualquer atualização futura.
