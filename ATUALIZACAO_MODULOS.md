# Atualização dos módulos do Agro

Esta versão amplia o MVP para produtor rural e profissional do agro sem apagar usuários, propriedades, talhões ou atividades já cadastrados.

## Módulos incluídos

- Equipe e acessos: o produtor compartilha uma propriedade com um usuário já cadastrado e define visualização, edição ou gerenciamento.
- Visitas técnicas: o profissional registra visita agendada ou realizada, resumo e próximos passos.
- Recomendações: o profissional publica orientações com prioridade e prazo; o produtor pode aceitar ou marcar como concluída.
- Tarefas: criação, atribuição a membro da propriedade e atualização de status.
- Relatórios: indicadores operacionais e impressão/salvamento em PDF pelo navegador.
- Arquivos: upload de PDF/JPG/PNG/WEBP até 5 MB e download autenticado.
- Controle de acesso: os módulos verificam vínculo do usuário à propriedade e permissões antes de gravar ou baixar dados.

## Publicação no InfinityFree

1. Baixe o ZIP atualizado do branch main: https://github.com/meiobesta333/Agro/archive/refs/heads/main.zip
2. Faça backup dos arquivos atuais e do banco pelo painel da hospedagem.
3. Envie os arquivos atualizados para htdocs, preservando o arquivo config.php atual. Não substitua config.php pelo exemplo.
4. Garanta que a pasta storage e seu arquivo .htaccess também sejam enviados. Ela guarda anexos privados.
5. Acesse uma única vez https://germina.free.nf/upgrade.php.
6. Se aparecer “Atualização concluída”, exclua imediatamente htdocs/upgrade.php.
7. Acesse https://germina.free.nf/index.php e teste os módulos com as duas contas.
8. Mantenha install.php e diagnostico.php excluídos se a instalação inicial já foi concluída.

## Primeiro teste de colaboração

1. Entre com a conta de produtor e cadastre uma propriedade.
2. Crie uma segunda conta com perfil Profissional do agro.
3. Entre novamente como produtor, abra Equipe e acessos e convide o e-mail da conta profissional.
4. Saia e entre como profissional: a propriedade compartilhada deverá aparecer em sua lista.
5. Teste visitas, recomendações, tarefas e anexos.

## Limitações desta entrega

Esta é uma primeira expansão funcional do MVP, não uma declaração de prontidão para produção. Ainda faltam testes completos no servidor real, edição/exclusão geral de registros, notificações por e-mail/push, agenda com calendário, relatórios PDF gerados no servidor, validação agronômica de recomendações, quotas do plano freemium, backups automatizados e revisão de segurança independente. O armazenamento de arquivos depende do espaço disponível na hospedagem gratuita.
