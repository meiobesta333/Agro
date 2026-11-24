# Germina — Especificação funcional e técnica: Gestão Compartilhada

**Status:** especificação proposta para orientar o desenvolvimento.  
**Repositório:** `meiobesta333/Agro` — branch `main`.  
**Stack atual:** PHP, PDO, MySQL/MariaDB, HTML/CSS/JavaScript; hospedagem compartilhada.  
**Princípio:** a propriedade é o centro dos dados e o produtor mantém o controle do acesso.

> Este documento não significa que as funcionalidades abaixo já estejam implementadas. É a arquitetura-alvo e o plano de execução. A implementação deve ser incremental, com backup e migrações compatíveis com os dados existentes.

## 1. Objetivo e escopo

Permitir que produtores e profissionais habilitados trabalhem remotamente no mesmo ambiente de uma propriedade rural. O produtor compartilha um código de convite; o profissional solicita acesso; o produtor aprova e define permissões. Após a aprovação, os dois perfis consultam e registram informações conforme suas permissões.

Escopo funcional:
- vínculo seguro entre profissional e propriedade;
- permissões por propriedade e por módulo;
- painel de carteira para profissionais e painel de gestão para produtores;
- recomendações, tarefas, visitas, diário de campo e operações;
- documentos, fotos e vídeos compartilhados;
- mensagens e notificações;
- relatórios e histórico de auditoria;
- módulo de receituário agronômico, com validação de habilitação e requisitos legais;
- arquitetura preparada para uso com conexão instável e sincronização posterior.

Fora do primeiro lançamento: videochamada própria, assinatura eletrônica própria, integração automática com sistemas governamentais, diagnóstico agronômico automatizado e emissão de receituário com validade legal sem integração/validação adequada.

## 2. Perfis e responsabilidades

### Produtor
- É proprietário/administrador da propriedade no sistema.
- Gera, pausa, revoga e renova códigos de convite.
- Aprova ou rejeita pedidos de acesso.
- Concede e revoga permissões.
- Consulta o histórico de ações.
- Pode enviar fotos, solicitar orientação, aceitar recomendações e registrar execução.
- Não pode alterar ou assinar uma receita em nome do responsável técnico.

### Profissional do agro
- Mantém seu cadastro e dados profissionais.
- Solicita vínculo usando código da propriedade.
- Só acessa dados depois da aprovação.
- Atua apenas dentro das permissões concedidas.
- Pode elaborar recomendações e documentos técnicos dentro de sua atribuição profissional.
- Não pode compartilhar novamente o acesso nem administrar membros, salvo autorização explícita futura.

### Administrador da plataforma
- Suporte operacional com acesso mínimo e rastreado.
- Não deve consultar conteúdo privado por padrão.
- Acesso excepcional deve ter justificativa, registro de auditoria e política de retenção.

## 3. Fluxo do código de propriedade

1. Produtor abre **Propriedade → Compartilhamento → Convidar profissional**.
2. Sistema gera código aleatório criptograficamente seguro, exibido uma única vez ou enquanto ativo.
3. Código pertence a uma propriedade, tem validade, limite de usos e pode ser revogado.
4. Profissional abre **Minha carteira → Adicionar propriedade**, informa o código e envia solicitação.
5. Sistema valida código, conta ativa e perfil profissional; cria pedido pendente, sem liberar dados.
6. Produtor recebe notificação e vê nome, e-mail e registro profissional informado pelo solicitante.
7. Produtor aprova ou rejeita; na aprovação escolhe um modelo de permissões e pode personalizar.
8. O profissional passa a ver a propriedade em sua carteira.
9. Produtor ou profissional pode solicitar encerramento do vínculo; apenas produtor/administrador autorizado efetiva a revogação.
10. Revogação invalida imediatamente novas consultas e operações. Arquivos baixados anteriormente para dispositivos não podem ser apagados remotamente e isso deve ser informado.

Regras:
- Código nunca será o ID sequencial da propriedade.
- Gerar pelo menos 128 bits de entropia com `random_bytes`; exibição amigável em grupos, sem caracteres ambíguos.
- Salvar somente hash do código (por exemplo SHA-256); comparar hash em tempo constante.
- Não incluir nome/endereço da propriedade no código.
- Validade sugerida: 7 dias, configurável; limite padrão de 1 vínculo aprovado por código.
- Limitar tentativas por conta/IP e registrar tentativas inválidas.
- Código expirado, revogado ou usado não pode ser reutilizado.
- Código não concede acesso por si só: sempre exige aprovação do produtor.
- Não permitir que um usuário aprove a própria solicitação.
- O profissional deve ter perfil `professional`; dados de registro profissional são validados conforme política do produto, sem presumir habilitação apenas pelo perfil.

## 4. Permissões

As permissões atuais gerais `view/edit/manage` são insuficientes para o escopo. Evoluir para permissões específicas, mantendo compatibilidade com registros existentes.

Permissões sugeridas:
- `property.view`: visualizar dados gerais, culturas, talhões e histórico permitido.
- `field.view`, `field.manage`.
- `diary.view`, `diary.create`, `diary.manage`.
- `equipment.view`, `equipment.manage`.
- `stock.view`, `stock.manage`.
- `recommendation.view`, `recommendation.create`, `recommendation.manage`.
- `task.view`, `task.create`, `task.manage`.
- `visit.view`, `visit.create`, `visit.manage`.
- `file.view`, `file.upload`, `file.delete_own`, `file.manage`.
- `message.view`, `message.send`.
- `report.view`, `report.export`.
- `prescription.view`, `prescription.draft`, `prescription.issue`, `prescription.cancel`.
- `member.view`, `member.manage` (reservado ao produtor por padrão).

Modelos iniciais:
- **Leitura:** consulta informações e documentos permitidos.
- **Assistência técnica:** leitura, mensagens, arquivos, visitas e recomendações.
- **Gestão técnica:** assistência técnica + tarefas, diário e operações.
- **Personalizado:** produtor seleciona permissões individualmente.

Princípios:
- O proprietário sempre mantém administração total da própria propriedade.
- Permissão é verificada no servidor em cada requisição, não apenas ocultando botões.
- Acesso a um módulo não implica acesso a todos os dados ou ações do módulo.
- Ações de alto impacto (receita, exclusão, revogação, mudança de permissão) exigem validação específica.
- A concessão de permissões não pode permitir que profissional altere proprietário ou transfira propriedade.
- Ao revogar vínculo, revogar também sessões/tokens de sincronização específicos da propriedade, quando existirem.

## 5. Experiência e telas

### Produtor
1. **Detalhe da propriedade:** resumo, membros e status do compartilhamento.
2. **Compartilhamento:** código ativo, validade, copiar código, renovar/revogar.
3. **Solicitações recebidas:** profissional, e-mail, registro informado, data e ações aprovar/rejeitar.
4. **Permissões:** modelos e permissões personalizadas por profissional.
5. **Central da propriedade:** atividades recentes, recomendações pendentes, mensagens, arquivos, relatórios e alertas.
6. **Receitas:** consultar, comentar/solicitar esclarecimento e acompanhar situação; não editar conteúdo técnico emitido pelo profissional.

### Profissional
1. **Minha carteira:** propriedades autorizadas, produtor responsável, localização resumida e pendências.
2. **Adicionar propriedade:** campo para código e solicitação de acesso.
3. **Visão da propriedade:** dados autorizados, talhões, histórico, equipe e documentos.
4. **Central de atendimento:** mensagens, pedidos de orientação e tarefas.
5. **Área técnica:** recomendações, visitas, relatórios e receitas.
6. **Agenda e pendências:** vencimentos, retornos, tarefas e revisões.

### Navegação mobile
- Ações principais acessíveis com uma mão.
- Botão voltar consistente nas telas internas.
- Formulários em etapas curtas, salvamento de rascunho e feedback claro.
- Captura de foto diretamente pela câmera quando o navegador oferecer suporte.
- Não exigir instalação de aplicativo para os fluxos essenciais.

## 6. Comunicação, imagens e documentos

### Mensagens
- Conversas vinculadas a uma propriedade e, opcionalmente, a um talhão ou registro.
- Mensagem de texto, anexos, data/hora, remetente e status de leitura.
- Comentários contextuais em arquivo, recomendação, tarefa e receita.
- Notificações no painel; e-mail pode ser adicionado depois.
- Evitar prometer chat em tempo real no primeiro ciclo; atualização por consulta periódica ou recarregamento é suficiente para o MVP.

### Arquivos
- Tipos iniciais: JPEG, PNG, WebP, PDF; vídeo somente após confirmar capacidade da hospedagem.
- Validar extensão, MIME real, tamanho máximo e conteúdo; renomear com identificador aleatório.
- Não confiar no nome ou MIME enviado pelo navegador.
- Armazenar fora da pasta pública, ou bloquear completamente execução e acesso direto com regras do servidor. Preferir download/visualização por PHP após checagem de permissão.
- Metadados: propriedade, talhão opcional, remetente, nome original, nome interno, MIME, tamanho, hash, categoria, legenda e data.
- Criar miniaturas de imagem somente se houver biblioteca/extensão disponível.
- Limites iniciais configuráveis por arquivo e por propriedade; mostrar espaço utilizado.
- Remoção lógica primeiro; exclusão física após prazo de retenção.
- Registrar download e exclusão de arquivos sensíveis.

### Cópias locais e sincronização
A aplicação web pode oferecer download para a galeria/arquivos do dispositivo conforme suporte do navegador e permissão do usuário. O navegador não pode salvar silenciosamente em qualquer pasta do aparelho.
- Primeiro lançamento: upload online, visualização e download manual.
- Próxima etapa: PWA com cache de interface e rascunhos locais.
- Etapa avançada: fila de uploads offline usando IndexedDB, identificador único por operação, retentativas e tela de conflitos.
- Não sincronizar automaticamente arquivos privados após revogação.
- Avisar que arquivos já baixados permanecem no aparelho do destinatário.

## 7. Recomendações, operações e relatórios

Reutilizar e ampliar as estruturas atuais:
- `recommendations`: associar talhão, prioridade, autor, status, prazo, resposta do produtor e anexos.
- `tasks`: responsável, criador, status, prazo, comentários e evidências.
- `visits`: diferenciar presencial/remota, agenda, conclusão, resumo, próximos passos e anexos.
- `activities` e tabelas `farm_*`: manter a propriedade como chave de escopo e registrar autor.
- Relatórios: filtros por período, talhão, cultura, categoria e autor; exportação PDF com identificação do emissor e data de geração.
- Relatório deve indicar claramente dados informados, recomendações e operações efetivamente registradas; não confundir recomendação com aplicação realizada.

## 8. Receituário agronômico e registros técnicos

Este módulo é de maior risco regulatório e deve ser liberado somente depois de revisão jurídica/técnica e confirmação das regras aplicáveis no estado e no país.

Fluxo proposto:
1. Profissional inicia rascunho associado à propriedade e talhão/cultura.
2. Informa diagnóstico/justificativa, produto, ingrediente ativo quando aplicável, dose, unidade, volume, área, modalidade e época de aplicação, intervalo de segurança e demais campos exigidos.
3. Sistema valida campos obrigatórios e dados de cadastro, mas não prescreve nem calcula dose automaticamente sem fonte validada.
4. Profissional revisa e confirma sua identidade e dados de habilitação/registro.
5. Documento só assume status de emitido após assinatura e requisitos legais aplicáveis atendidos.
6. Produtor recebe documento e pode registrar ciência/recebimento.
7. Aplicação é registrada separadamente, com data, talhão, executor, produto, quantidade e evidências.
8. Correção não sobrescreve documento emitido: gera nova versão/cancelamento com trilha de auditoria.

Estados:
- `draft`, `under_review`, `issued`, `acknowledged`, `partially_applied`, `applied`, `cancelled`, `superseded`.

Requisitos mínimos:
- Não chamar um PDF simples de receituário legalmente válido.
- Não permitir emissão por usuário sem habilitação aplicável.
- Registrar nome, conselho/órgão, número de registro, UF e situação de validação, conforme aplicável.
- Assinatura digital/eletrônica e integração oficial só após análise dos requisitos legais e fornecedores.
- Guardar versão imutável do documento emitido, hash, autor, data, assinatura/identificador de validação e eventos.
- Exibir advertências, restrições de uso, EPI, armazenamento, descarte, reentrada e carência quando exigidos pela legislação/rótulo/bula.
- Catálogo de produtos deve ter fonte, data de atualização e responsável; nunca presumir que um produto está autorizado para toda cultura/região.
- O sistema não substitui rótulo, bula, orientação oficial nem julgamento do responsável técnico.

## 9. Modelo de dados proposto

Nomes propostos; ajustar tipos e convenções após conferir o schema real do banco antes da migração.

### Compartilhamento
- `property_access_codes`: id, property_id, code_hash, created_by, expires_at, max_uses, uses_count, revoked_at, created_at.
- `property_access_requests`: id, property_id, requested_by, access_code_id, status, requested_at, decided_by, decided_at, rejection_reason.
- `property_members` existente: manter vínculo único property_id/user_id; acrescentar status, approved_at, revoked_at, invited_by/approved_by quando necessário. Evitar apagar histórico ao revogar.
- `property_member_permissions`: id, property_id, user_id, permission_key, granted_by, created_at; chave única property_id/user_id/permission_key.
- `property_member_events`: histórico de concessão, alteração e revogação.

### Comunicação e arquivos
- `conversations`: id, property_id, subject, created_by, created_at, last_message_at.
- `conversation_members`: conversation_id, user_id, joined_at, last_read_at.
- `messages`: id, conversation_id, sender_id, body, created_at, edited_at, deleted_at.
- `message_attachments`: message_id, attachment_id.
- Evoluir `attachments` ou criar `shared_files`: propriedade, talhão, remetente, storage_key, MIME validado, tamanho, hash, legenda, categoria, status e timestamps.
- `notifications`: user_id, property_id, type, entity_type, entity_id, read_at, created_at.
- `audit_logs`: actor_id, property_id, action, entity_type, entity_id, before_json, after_json, ip_hash/metadata mínima, created_at.

### Receituário
- `agronomic_prescriptions`: id, property_id, field_id, professional_id, version, status, crop, target, diagnosis, area, application_method, instructions, issued_at, document_hash, supersedes_id, created_at.
- `prescription_products`: prescription_id, product_name, active_ingredient, registration_number, dose, dose_unit, quantity, quantity_unit, target, application_notes.
- `professional_credentials`: user_id, credential_type, registration_number, state, verification_status, verified_at, evidence_reference.
- `prescription_events`: prescription_id, actor_id, event_type, details_json, created_at.
- `application_records`: prescription_id, property_id, field_id, applied_by, application_date, quantity, area, notes, created_at.

Toda tabela de conteúdo deve carregar `property_id` ou chegar a ele por relação validada. Criar índices por propriedade/data/status e chaves estrangeiras com política de exclusão adequada. Dados de auditoria e documentos emitidos não devem ser apagados em cascata inadvertidamente.

## 10. Arquitetura PHP e integração com o código existente

Manter a stack atual na primeira versão, sem introduzir Supabase ou exigir Node:
- PHP com PDO e prepared statements.
- MySQL/MariaDB com InnoDB e utf8mb4.
- Sessões seguras e CSRF já existentes em `app/bootstrap.php`.
- Reutilizar `property_access()`, `user_properties()` e a estrutura `workspace.php`, mas centralizar autorização granular em funções dedicadas.
- Separar gradualmente handlers de POST e consultas por módulo em arquivos `app/services/` e `app/repositories/`, evitando crescer indefinidamente o arquivo principal.
- Endpoints POST para ações mutáveis; resposta JSON apenas quando necessária para upload assíncrono/sincronização.
- Todas as consultas devem filtrar a propriedade autorizada, não apenas o ID do registro.
- Upload e download sempre passam por autorização no servidor.
- Usar transações em operações que alterem estoque, vínculo, permissões ou status de documentos.
- Não expor caminhos físicos, configuração, erros SQL ou stack traces ao usuário.
- `config.php` permanece fora do Git; nunca sobrescrever nem solicitar senha no chat.

Funções de domínio recomendadas:
- `require_property_permission($propertyId, $permissionKey)`
- `create_property_access_code($propertyId, $expiresAt, $maxUses)`
- `request_property_access($code, $professionalId)`
- `approve_property_access($requestId, $producerId, $permissionSet)`
- `revoke_property_member($propertyId, $memberId, $actorId)`
- `store_private_attachment($propertyId, $file, $actorId)`
- `authorize_attachment_download($attachmentId, $actorId)`
- `write_audit_event(...)`

## 11. Segurança, privacidade e LGPD

- Verificar autorização em todas as operações e downloads.
- CSRF em formulários; validar método HTTP.
- Escapar saída HTML; prepared statements; validar IDs e enumerações.
- Rate limit em tentativa de código, login, mensagens e upload.
- Política de senha e sessão; cookies HttpOnly, Secure em HTTPS e SameSite.
- Validar uploads por MIME real, extensão permitida, tamanho e conteúdo.
- Nome interno aleatório; impedir execução de PHP/HTML/SVG enviado por usuário.
- Armazenar arquivos privados fora de `htdocs` sempre que possível; caso a hospedagem não permita, bloquear acesso direto por configuração e testar.
- Criptografia em trânsito via HTTPS; avaliar criptografia em repouso para documentos sensíveis.
- Minimização de dados, finalidade, transparência, canal para correção/exclusão e política de retenção.
- Registrar consentimento/ciência quando necessário.
- Backups protegidos e teste periódico de restauração.
- Auditoria de acesso, mudanças de permissão, emissão/cancelamento de receita e downloads sensíveis.
- Não registrar senhas, tokens ou códigos em logs.
- Definir procedimento de incidente e contato de privacidade.

## 12. Limites da hospedagem e decisões operacionais

Antes de habilitar fotos e vídeos em escala, medir:
- espaço disponível e limite de inode;
- tamanho máximo de upload do PHP (`upload_max_filesize`, `post_max_size`);
- limite de execução e memória;
- regras de armazenamento e tráfego da hospedagem;
- suporte a HTTPS, cron e extensões PHP necessárias.

MVP deve começar com imagens compactadas e PDF, limite configurável e barra de uso. Vídeos, processamento de imagens e notificações em tempo real ficam para uma fase posterior se a hospedagem atual não suportar.

Não guardar arquivos de usuários no repositório Git. Não publicar `config.php`, uploads, backups ou dados reais.

## 13. Migrações e compatibilidade

- Fazer backup do banco e dos arquivos antes de cada migração.
- Criar migrations incrementais, idempotentes e com mensagens claras.
- Não executar novamente `install.php`.
- Não sobrescrever `config.php`, apagar `storage/` nem apagar tabelas existentes.
- Não executar migrations em produção sem validar primeiro em cópia/ambiente de teste.
- Criar índices e tabelas novas antes de alterar fluxos existentes.
- Migrar permissões antigas para um conjunto equivalente; conferir usuários e vínculos atuais.
- Manter rollback documentado quando possível; para alterações de dados, preparar restauração via backup.
- Scripts de upgrade de uso único devem ser removidos do servidor após sucesso e protegidos contra acesso público.

## 14. Plano de implementação

### Fase 0 — Preparação
- Conferir schema completo atual e versão PHP/MySQL da hospedagem.
- Inventariar uso atual de `property_members`, `attachments`, `storage/` e permissões.
- Fazer backup e preparar ambiente de teste.
- Definir limites de upload e política de privacidade.

### Fase 1 — Código e vínculo
- Migração de códigos, solicitações e histórico de membros.
- Telas de gerar/copiar código, solicitar acesso, aprovar/rejeitar e revogar.
- Carteira do profissional e painel de compartilhamento do produtor.
- Testes de acesso cruzado e tentativas com códigos inválidos/expirados.

### Fase 2 — Permissões e auditoria
- Catálogo de permissões granulares e modelos.
- Verificação no servidor em todos os handlers atuais.
- Auditoria de mudanças e ações críticas.
- Compatibilidade com vínculos existentes.

### Fase 3 — Arquivos e mensagens
- Armazenamento privado, upload seguro, download autorizado e metadados.
- Mensagens, comentários e notificações internas.
- Cotas, exclusão lógica e logs.
- Testes em Android/iOS e desktop.

### Fase 4 — Gestão técnica
- Melhorar recomendações, tarefas, visitas e Diário de Campo compartilhado.
- Relatórios e exportação PDF.
- Evidências fotográficas e histórico de execução.

### Fase 5 — Receituário
- Revisão técnica e jurídica das exigências aplicáveis.
- Cadastro e verificação de credenciais profissionais.
- Rascunho, validação, emissão, assinatura, versão e cancelamento.
- Registro separado da aplicação realizada.
- Testes de auditoria e integridade documental.

### Fase 6 — PWA e sincronização
- Instalação opcional, cache seguro, rascunhos locais e fila de upload.
- Política de conflito e sincronização idempotente.
- Não cachear respostas privadas em dispositivos compartilhados sem proteção.
- Testes de revogação com dispositivo offline.

## 15. Critérios de aceite

1. Código aleatório não revela ID nem dados da propriedade.
2. Código expirado/revogado não permite solicitação.
3. Solicitação não libera dados antes da aprovação.
4. Produtor consegue aprovar, rejeitar e revogar.
5. Profissional enxerga somente propriedades vinculadas.
6. Usuário sem permissão recebe 403/negação mesmo acessando URL diretamente.
7. Usuário de uma propriedade não acessa registros de outra alterando IDs.
8. Upload rejeita arquivo proibido, MIME falso e arquivo acima do limite.
9. Arquivo privado não abre por URL pública direta.
10. Revogação bloqueia mensagens, consultas, novos downloads e novas operações.
11. Alterações críticas aparecem no histórico de auditoria.
12. Receita emitida é imutável; correção cria nova versão/evento.
13. Layout funciona em celular e computador.
14. Migração preserva contas, propriedades, registros, configuração e arquivos existentes.
15. Restauração de backup foi testada antes de liberar em produção.

## 16. Decisões padrão recomendadas

- Um código por propriedade, renovável, validade de 7 dias e uso único para novo vínculo.
- Aprovação manual obrigatória do produtor.
- Permissões granulares com modelos prontos.
- Profissional não recebe administração de membros por padrão.
- Arquivos privados no servidor e cópia local somente mediante ação explícita do usuário.
- Começar com imagens e PDF; vídeos depois.
- Comunicação interna simples antes de chat em tempo real.
- Receituário em fase separada, condicionado a revisão regulatória.
- Implementar em fases, preservando PHP/MySQL e hospedagem atual inicialmente.

## 17. Próxima atividade de desenvolvimento

Antes de escrever a primeira migration, confirmar o schema real de `users`, `properties`, `property_members`, `attachments`, `fields`, `activities` e `storage/`, além das versões e limites do PHP/MySQL no servidor. Em seguida, implementar somente a Fase 1 em branch/commit isolado, revisar o diff, testar em banco de cópia e só então orientar publicação na hospedagem.

## Implementação adicional — Consultas internas

O módulo **Consultas** oferece uma sala de conversa vinculada a cada propriedade compartilhada. O produtor responsável e todos os profissionais com acesso aprovado àquela propriedade participam do mesmo histórico.

### Escopo da primeira versão
- Mensagens de texto com histórico persistido no banco.
- Envio de imagens, PDF, documentos comuns, áudio e vídeo.
- Pré-visualização de imagens e reprodução de áudio/vídeo compatíveis com o navegador.
- Atualização automática das mensagens a cada 10 segundos, sem serviço de mensageria externo.
- Controle de acesso por propriedade: somente proprietário e membros ativos podem ler, enviar e baixar anexos.
- Arquivos com nomes aleatórios no diretório privado `storage/`; o download passa por endpoint autenticado e não expõe o caminho físico.
- Validação de MIME no servidor por `finfo`, lista de formatos permitidos e limite de 20 MB por arquivo, sujeito aos limites reais de PHP e da hospedagem.

### Estrutura de dados
A tabela `consultation_messages` armazena propriedade, remetente, texto opcional, metadados do anexo e data. A sala é compartilhada por propriedade: todos os participantes autorizados daquela propriedade veem o mesmo histórico.

### Limitações da primeira versão
- Não há chamadas de voz/vídeo ao vivo; o módulo envia gravações e arquivos de áudio/vídeo.
- Não há criptografia ponta a ponta; o acesso é protegido pela autenticação e autorização da aplicação e pelo armazenamento privado.
- Não há confirmação de leitura, indicador de digitação, notificações push ou conversas privadas individuais entre vários profissionais da mesma propriedade.
- A atualização de novas mensagens usa consulta periódica a cada 10 segundos.
- O tamanho efetivo de upload depende das diretivas `upload_max_filesize` e `post_max_size`, além do espaço e da transferência disponíveis na hospedagem.

### Implantação
Após publicar os arquivos, fazer backup do banco e executar `upgrade_consultas.php` uma única vez. Confirmar a criação da tabela `consultation_messages` e excluir o script de migração do servidor. Nunca executar `install.php` para esta atualização.
