<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
$user=current_user();
if(!$user){redirect('index.php?page=login');}
$page=$_GET['page']??'dashboard';
$errors=[];$notice=$_SESSION['notice']??'';unset($_SESSION['notice']);
function module_properties(): array { return user_properties(); }
function module_property(int $id): ?array { return property_access($id); }
function module_require_property(int $id,bool $edit=false): array {
 $p=module_property($id);
 if(!$p || ($edit && !can_edit_property($p))) throw new RuntimeException('Você não tem permissão para esta propriedade.');
 return $p;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
  $action=(string)($_POST['action']??'');
  if($action==='invite'){
   $pid=(int)($_POST['property_id']??0);$p=module_require_property($pid,true);
   if((int)$p['owner_id']!==(int)$user['id'])throw new RuntimeException('Somente o produtor responsável pode convidar profissionais.');
   $email=strtolower(trim((string)($_POST['email']??'')));$permission=$_POST['permission']??'view';
   if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail válido.');
   if(!in_array($permission,['view','edit','manage'],true))$permission='view';
   $st=db()->prepare("SELECT id,role FROM users WHERE email=?");$st->execute([$email]);$member=$st->fetch();
   if(!$member)throw new RuntimeException('Esse usuário ainda não possui conta no Agro. Peça que faça o cadastro primeiro.');
   if((int)$member['id']===(int)$user['id'])throw new RuntimeException('Você já é o responsável pela propriedade.');
   $st=db()->prepare("INSERT INTO property_members(property_id,user_id,permission,invited_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission)");
   $st->execute([$pid,$member['id'],$permission,$user['id']]);flash('Acesso compartilhado com sucesso.');
   redirect('index.php?page=team&id='.$pid);
  }
  if($action==='remove_member'){
   $pid=(int)$_POST['property_id'];$p=module_require_property($pid,true);
   if((int)$p['owner_id']!==(int)$user['id'])throw new RuntimeException('Somente o responsável pode remover membros.');
   $st=db()->prepare('DELETE FROM property_members WHERE property_id=? AND user_id=?');$st->execute([$pid,(int)$_POST['member_id']]);flash('Acesso removido.');redirect('index.php?page=team&id='.$pid);
  }
  if($action==='visit'){
   $pid=(int)$_POST['property_id'];module_require_property($pid,true);
   if($user['role']!=='professional')throw new RuntimeException('Somente profissionais podem registrar visitas técnicas.');
   $date=$_POST['visit_date']??date('Y-m-d');$summary=trim((string)($_POST['summary']??''));$next=trim((string)($_POST['next_steps']??''));
   if($summary==='')throw new RuntimeException('Descreva o atendimento ou a visita.');
   $visitStatus=in_array($_POST['visit_status']??'',['scheduled','completed'],true)?$_POST['visit_status']:'completed';
   $st=db()->prepare("INSERT INTO visits(property_id,professional_id,visit_date,status,summary,next_steps) VALUES(?,?,?,?,?,?)");$st->execute([$pid,$user['id'],$date,$visitStatus,$summary,$next?:null]);flash('Visita técnica registrada.');redirect('index.php?page=visits');
  }
  if($action==='recommendation'){
   $pid=(int)$_POST['property_id'];module_require_property($pid,true);
   if($user['role']!=='professional')throw new RuntimeException('Recomendações técnicas são registradas pelo profissional responsável.');
   $title=trim((string)$_POST['title']);$desc=trim((string)$_POST['description']);$priority=in_array($_POST['priority']??'',['low','medium','high'],true)?$_POST['priority']:'medium';
   if($title===''||$desc==='')throw new RuntimeException('Preencha o título e a recomendação.');
   $st=db()->prepare('INSERT INTO recommendations(property_id,author_id,title,description,priority,due_date) VALUES(?,?,?,?,?,?)');$st->execute([$pid,$user['id'],$title,$desc,$priority,$_POST['due_date']?:null]);flash('Recomendação publicada.');redirect('index.php?page=recommendations');
  }
  if($action==='recommendation_status'){
   $id=(int)$_POST['id'];$status=in_array($_POST['status']??'',['accepted','done','cancelled'],true)?$_POST['status']:'accepted';
   $st=db()->prepare("SELECT r.id,p.owner_id FROM recommendations r JOIN properties p ON p.id=r.property_id LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE r.id=? AND (p.owner_id=? OR pm.user_id=?)");$st->execute([$user['id'],$id,$user['id'],$user['id']]);if(!$st->fetch())throw new RuntimeException('Recomendação não encontrada.');
   $st=db()->prepare('UPDATE recommendations SET status=? WHERE id=?');$st->execute([$status,$id]);flash('Status da recomendação atualizado.');redirect('index.php?page=recommendations');
  }
  if($action==='task'){
   $pid=(int)$_POST['property_id'];module_require_property($pid,true);$title=trim((string)$_POST['title']);
   if($title==='')throw new RuntimeException('Informe o título da tarefa.');
   $assigned=(int)($_POST['assigned_to']??0)?:null;
   if($assigned){$st=db()->prepare('SELECT user_id FROM property_members WHERE property_id=? AND user_id=?');$st->execute([$pid,$assigned]);if(!$st->fetch() && (int)module_property($pid)['owner_id']!==$assigned)throw new RuntimeException('O responsável precisa ter acesso à propriedade.');}
   $st=db()->prepare('INSERT INTO tasks(property_id,created_by,assigned_to,title,description,due_date) VALUES(?,?,?,?,?,?)');$st->execute([$pid,$user['id'],$assigned,$title,trim((string)$_POST['description'])?:null,$_POST['due_date']?:null]);flash('Tarefa criada.');redirect('index.php?page=tasks');
  }
  if($action==='task_status'){
   $id=(int)$_POST['id'];$status=in_array($_POST['status']??'',['open','doing','done','cancelled'],true)?$_POST['status']:'open';
   $st=db()->prepare("SELECT t.id FROM tasks t JOIN properties p ON p.id=t.property_id LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE t.id=? AND (p.owner_id=? OR pm.user_id=? OR t.assigned_to=?)");$st->execute([$user['id'],$id,$user['id'],$user['id'],$user['id']]);if(!$st->fetch())throw new RuntimeException('Tarefa não encontrada.');
   $st=db()->prepare('UPDATE tasks SET status=? WHERE id=?');$st->execute([$status,$id]);flash('Tarefa atualizada.');redirect('index.php?page=tasks');
  }
  if($action==='upload'){
   $pid=(int)$_POST['property_id'];module_require_property($pid,true);
   if(empty($_FILES['attachment'])||$_FILES['attachment']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione um arquivo válido.');
   $f=$_FILES['attachment'];if($f['size']>5*1024*1024)throw new RuntimeException('O arquivo deve ter no máximo 5 MB.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
   $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
   if(!isset($allowed[$mime]))throw new RuntimeException('Formato não permitido. Envie PDF, JPG, PNG ou WEBP.');
   $dir=__DIR__.'/storage';if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('Não foi possível preparar o armazenamento.');
   $stored=bin2hex(random_bytes(18)).'.'.$allowed[$mime];
   if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$stored))throw new RuntimeException('Falha ao salvar o arquivo.');
   $st=db()->prepare('INSERT INTO attachments(property_id,uploaded_by,original_name,stored_name,mime_type,file_size) VALUES(?,?,?,?,?,?)');$st->execute([$pid,$user['id'],basename($f['name']),$stored,$mime,(int)$f['size']]);
   flash('Arquivo enviado com segurança.');redirect('index.php?page=attachments&id='.$pid);
  }
 }catch(Throwable $e){$errors[]=$e instanceof PDOException?'Não foi possível salvar. Verifique os dados e tente novamente.':$e->getMessage();}
}
$props=module_properties();$ids=array_map(fn($p)=>(int)$p['id'],$props);
$idsSql=$ids?implode(',',array_fill(0,count($ids),'?')):'0';
function module_rows(string $sql,array $ids): array { if(!$ids)return []; $st=db()->prepare($sql);$st->execute($ids);return $st->fetchAll(); }
$activities=$ids?module_rows("SELECT a.*,p.name property_name FROM activities a JOIN properties p ON p.id=a.property_id WHERE a.property_id IN ($idsSql) ORDER BY a.activity_date DESC LIMIT 100",$ids):[];
$visits=$ids?module_rows("SELECT v.*,p.name property_name,u.name professional_name FROM visits v JOIN properties p ON p.id=v.property_id JOIN users u ON u.id=v.professional_id WHERE v.property_id IN ($idsSql) ORDER BY v.visit_date DESC LIMIT 100",$ids):[];
$recs=$ids?module_rows("SELECT r.*,p.name property_name,u.name author_name FROM recommendations r JOIN properties p ON p.id=r.property_id JOIN users u ON u.id=r.author_id WHERE r.property_id IN ($idsSql) ORDER BY r.created_at DESC LIMIT 100",$ids):[];
$tasks=$ids?module_rows("SELECT t.*,p.name property_name,u.name assignee_name FROM tasks t JOIN properties p ON p.id=t.property_id LEFT JOIN users u ON u.id=t.assigned_to WHERE t.property_id IN ($idsSql) ORDER BY t.due_date IS NULL,t.due_date,t.created_at DESC LIMIT 100",$ids):[];
$fields=[];foreach($props as $p){$st=db()->prepare('SELECT id,name FROM fields WHERE property_id=?');$st->execute([$p['id']]);foreach($st->fetchAll() as $f)$fields[]=$f+['property_id'=>$p['id']];}
$titleMap=['dashboard'=>'Visão geral','team'=>'Equipe e acessos','visits'=>'Visitas técnicas','recommendations'=>'Recomendações','tasks'=>'Tarefas','reports'=>'Relatórios','attachments'=>'Arquivos','activities'=>'Diário de campo','properties'=>'Propriedades'];
$title=$titleMap[$page]??'Workspace';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> · Agro</title><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="layout"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">✳</span><span>Agro<small>GESTÃO NO CAMPO</small></span></a><div class="nav-label">MENU PRINCIPAL</div>
<?php foreach(['dashboard'=>'Visão geral','properties'=>'Propriedades','activities'=>'Diário de campo','team'=>'Equipe e acessos','visits'=>'Visitas técnicas','recommendations'=>'Recomendações','tasks'=>'Tarefas','reports'=>'Relatórios','attachments'=>'Arquivos'] as $key=>$label):?><a class="nav-link <?= $page===$key?'active':''?>" href="index.php<?= $key==='dashboard'?'':'?page='.$key?>">◫ <span><?=e($label)?></span></a><?php endforeach;?>
<div class="sidebar-bottom"><div class="avatar"><?=e(strtoupper(substr($user['name'],0,1)))?></div><div class="user-meta"><strong><?=e($user['name'])?></strong><small><?=$user['role']==='professional'?'Profissional do agro':'Produtor rural'?></small></div><form method="post" action="index.php?page=logout"><?=csrf_field()?><button class="logout">↗</button></form></div></aside>
<main class="main"><header class="topbar"><div><div class="breadcrumb">Workspace / <?=e($title)?></div><h1><?=e($title)?></h1></div><div class="top-actions"><span class="user-chip"><?=e($user['name'])?></span></div></header><div class="content">
<?php if($notice):?><div class="success"><?=e($notice)?></div><?php endif;?><?php foreach($errors as $err):?><div class="alert"><?=e($err)?></div><?php endforeach;?>
<?php if($page==='dashboard'):?>
<div class="welcome"><div><span class="eyebrow">SEU ESPAÇO DE TRABALHO</span><h2>Olá, <?=e(explode(' ',$user['name'])[0])?> 🌱</h2><p>Gestão integrada de propriedades, equipe e rotina no campo.</p></div><a class="btn primary" href="index.php?page=properties">+ Nova propriedade</a></div>
<div class="stats"><article class="stat"><span>Propriedades acessíveis</span><strong><?=count($props)?></strong><small>próprias e compartilhadas</small></article><article class="stat"><span>Talhões</span><strong><?=count($fields)?></strong><small>nas propriedades acessíveis</small></article><article class="stat"><span>Tarefas em aberto</span><strong><?=count(array_filter($tasks,fn($t)=>in_array($t['status'],['open','doing'],true)))?></strong><small>pendentes ou em andamento</small></article></div>
<div class="module-grid"><?php foreach(['team'=>'Equipe e acessos','visits'=>'Visitas técnicas','recommendations'=>'Recomendações','tasks'=>'Tarefas','reports'=>'Relatórios','attachments'=>'Arquivos'] as $k=>$v):?><a class="module-card" href="index.php?page=<?=$k?>"><strong><?=e($v)?></strong><small>Acessar módulo →</small></a><?php endforeach;?></div>
<?php elseif($page==='team'):?>
<div class="page-intro"><h2>Compartilhamento com profissionais</h2><p>O produtor pode conceder acesso a profissionais cadastrados no Agro.</p></div>
<?php foreach($props as $p):?><div class="panel"><div class="panel-head"><div><h3><?=e($p['name'])?></h3><p><?=!empty($p['is_owner'])?'Você é o responsável':'Acesso compartilhado'?></p></div></div>
<?php if(!empty($p['is_owner'])):?><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="invite"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><label>E-mail do profissional<input name="email" type="email" required placeholder="profissional@email.com"></label><label>Permissão<select name="permission"><option value="view">Visualizar</option><option value="edit">Registrar e editar</option><option value="manage">Gerenciar equipe</option></select></label><div class="form-submit"><button class="btn primary">Conceder acesso</button></div></form><?php endif;?>
<?php $st=db()->prepare('SELECT pm.*,u.name,u.email,u.role FROM property_members pm JOIN users u ON u.id=pm.user_id WHERE pm.property_id=?');$st->execute([$p['id']]);$members=$st->fetchAll();?><div class="table-wrap"><table><thead><tr><th>Usuário</th><th>Perfil</th><th>Permissão</th><?php if(!empty($p['is_owner'])):?><th></th><?php endif;?></tr></thead><tbody><?php foreach($members as $m):?><tr><td><?=e($m['name'])?><br><small><?=e($m['email'])?></small></td><td><?=e($m['role']==='professional'?'Profissional':'Produtor')?></td><td><?=e(['view'=>'Visualizar','edit'=>'Editar','manage'=>'Gerenciar'][$m['permission']]??$m['permission'])?></td><?php if(!empty($p['is_owner'])):?><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><input type="hidden" name="member_id" value="<?=(int)$m['user_id']?>"><button class="btn secondary">Remover</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div></div><?php endforeach;?>
<?php elseif($page==='visits'):?>
<div class="page-intro"><h2>Histórico e agenda de visitas</h2><p>Atendimentos registrados pelos profissionais nas propriedades compartilhadas.</p></div>
<?php if($user['role']==='professional'):?><div class="panel form-panel"><h3>Registrar visita técnica</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="visit"><label>Propriedade<select name="property_id" required><?php foreach($props as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select></label><label>Data<input type="date" name="visit_date" value="<?=date('Y-m-d')?>"></label><label>Status<select name="visit_status"><option value="scheduled">Agendada</option><option value="completed">Realizada</option></select></label><label class="span-2">Resumo da visita<textarea name="summary" required rows="3"></textarea></label><label class="span-2">Próximos passos<textarea name="next_steps" rows="2"></textarea></label><div class="form-submit"><button class="btn primary">Salvar visita</button></div></form></div><?php endif;?>
<div class="panel"><?php if(!$visits):?><div class="empty">Nenhuma visita registrada nas propriedades acessíveis.</div><?php else:?><div class="activity-list"><?php foreach($visits as $v):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($v['property_name'])?> · <?=date('d/m/Y',strtotime($v['visit_date']))?> · <?=e($v['status']==='scheduled'?'Agendada':($v['status']==='completed'?'Realizada':'Cancelada'))?></strong><p><?=e($v['summary'])?></p><?php if($v['next_steps']):?><p>Próximos passos: <?=e($v['next_steps'])?></p><?php endif;?><small>Profissional: <?=e($v['professional_name'])?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($page==='recommendations'):?>
<div class="page-intro"><h2>Recomendações técnicas</h2><p>Orientações registradas pelo profissional e acompanhadas pelo produtor.</p></div>
<?php if($user['role']==='professional'):?><div class="panel form-panel"><h3>Nova recomendação</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="recommendation"><label>Propriedade<select name="property_id" required><?php foreach($props as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select></label><label>Prioridade<select name="priority"><option value="low">Baixa</option><option value="medium" selected>Média</option><option value="high">Alta</option></select></label><label class="span-2">Título<input name="title" required></label><label class="span-2">Orientação técnica<textarea name="description" required rows="3"></textarea></label><label>Prazo<input type="date" name="due_date"></label><div class="form-submit"><button class="btn primary">Publicar recomendação</button></div></form></div><?php endif;?>
<div class="panel"><?php if(!$recs):?><div class="empty">Nenhuma recomendação disponível.</div><?php else:?><div class="activity-list"><?php foreach($recs as $r):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($r['title'])?> · <?=e(['low'=>'Baixa','medium'=>'Média','high'=>'Alta'][$r['priority']])?></strong><p><?=e($r['description'])?></p><small><?=e($r['property_name'])?> · <?=e($r['author_name'])?> · Status: <?=e($r['status'])?><?= $r['due_date']?' · Prazo '.date('d/m/Y',strtotime($r['due_date'])):''?></small><?php if($user['role']==='producer'&&$r['status']==='open'):?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="recommendation_status"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn primary" name="status" value="accepted">Aceitar</button><button class="btn secondary" name="status" value="done">Concluída</button></form><?php endif;?></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($page==='tasks'):?>
<div class="page-intro"><h2>Gestão de tarefas</h2><p>Organize pendências e acompanhe a execução.</p></div>
<div class="panel form-panel"><h3>Criar tarefa</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="task"><label>Propriedade<select name="property_id" required><?php foreach($props as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select></label><label>Prazo<input type="date" name="due_date"></label><label class="span-2">Título<input name="title" required></label><label class="span-2">Descrição<textarea name="description" rows="2"></textarea></label><label class="span-2">Responsável (ID de usuário, opcional)<input type="number" min="1" name="assigned_to" placeholder="Deixe vazio para não atribuir"></label><div class="form-submit"><button class="btn primary">Criar tarefa</button></div></form></div>
<div class="panel"><?php if(!$tasks):?><div class="empty">Nenhuma tarefa cadastrada.</div><?php else:?><div class="activity-list"><?php foreach($tasks as $t):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($t['title'])?></strong><p><?=e($t['description']??'')?></p><small><?=e($t['property_name'])?> · <?=e($t['status'])?><?= $t['due_date']?' · Prazo '.date('d/m/Y',strtotime($t['due_date'])):''?><?= $t['assignee_name']?' · Responsável '.$t['assignee_name']:''?></small><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="task_status"><input type="hidden" name="id" value="<?=(int)$t['id']?>"><select name="status"><option value="open" <?=$t['status']==='open'?'selected':''?>>Aberta</option><option value="doing" <?=$t['status']==='doing'?'selected':''?>>Em andamento</option><option value="done" <?=$t['status']==='done'?'selected':''?>>Concluída</option></select><button class="btn secondary">Atualizar</button></form></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($page==='reports'):?>
<div class="page-intro"><h2>Relatórios operacionais</h2><p>Resumo consolidado das propriedades às quais você tem acesso.</p></div><div class="stats"><article class="stat"><span>Propriedades</span><strong><?=count($props)?></strong></article><article class="stat"><span>Atividades</span><strong><?=count($activities)?></strong></article><article class="stat"><span>Visitas técnicas</span><strong><?=count($visits)?></strong></article></div><div class="panel"><div class="panel-head"><div><h3>Resumo por propriedade</h3><p>Indicadores para acompanhamento e impressão.</p></div><button class="btn primary" onclick="window.print()">Imprimir / salvar PDF</button></div><div class="table-wrap"><table><thead><tr><th>Propriedade</th><th>Área (ha)</th><th>Talhões</th></tr></thead><tbody><?php foreach($props as $p):?><tr><td><?=e($p['name'])?></td><td><?=e((string)($p['area_ha']??'—'))?></td><td><?=e((string)$p['field_count'])?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php elseif($page==='attachments'):?>
<div class="page-intro"><h2>Documentos e arquivos</h2><p>Envie documentos PDF e imagens vinculados às propriedades (máximo 5 MB).</p></div>
<?php foreach($props as $p):?><div class="panel"><h3><?=e($p['name'])?></h3><?php if(can_edit_property($p)):?><form method="post" enctype="multipart/form-data" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="upload"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required><button class="btn primary">Enviar arquivo</button></form><?php endif; $st=db()->prepare('SELECT a.*,u.name uploader FROM attachments a JOIN users u ON u.id=a.uploaded_by WHERE a.property_id=? ORDER BY a.created_at DESC');$st->execute([$p['id']]);$files=$st->fetchAll();?><div class="activity-list"><?php foreach($files as $f):?><div class="activity-item"><span class="activity-dot"></span><div><strong><a href="download.php?id=<?=(int)$f['id']?>"><?=e($f['original_name'])?></a></strong><small><?=e($f['uploader'])?> · <?=number_format($f['file_size']/1024,0)?> KB · <?=date('d/m/Y',strtotime($f['created_at']))?></small></div></div><?php endforeach;?><?php if(!$files):?><div class="empty compact">Nenhum arquivo enviado.</div><?php endif;?></div></div><?php endforeach;?>
<?php elseif($page==='activities'):?><div class="panel"><?php if(!$activities):?><div class="empty">Nenhuma atividade registrada.</div><?php else:?><div class="activity-list"><?php foreach($activities as $a):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($a['title'])?></strong><p><?=e($a['description']??'')?></p><small><?=e($a['property_name'])?> · <?=date('d/m/Y',strtotime($a['activity_date']))?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php else:?><div class="panel empty">Módulo não encontrado.</div><?php endif;?>
<footer class="footer">Agro · Gestão no campo · <?=date('Y')?></footer></div></main></div></body></html>