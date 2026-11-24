<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$page = $_GET['page'] ?? (current_user() ? 'dashboard' : 'login');
$publicPages = ['login', 'register'];
if (!current_user() && !in_array($page, $publicPages, true)) { redirect('index.php?page=login'); }
if (current_user() && in_array($page, $publicPages, true)) { redirect('index.php'); }
$modulePages = ['team','visits','recommendations','tasks','reports','attachments','activities'];
if (current_user() && in_array($page, $modulePages, true)) { require __DIR__ . '/workspace.php'; exit; }

if ($page === 'logout') {
    require_post();
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('index.php?page=login');
}

$errors = [];
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($page === 'login') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new RuntimeException('E-mail ou senha inválidos.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            redirect('index.php');
        }
        if ($page === 'register') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['producer', 'professional'], true) ? $_POST['role'] : 'producer';
            if (strlen($name) < 3) throw new RuntimeException('Informe seu nome completo.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
            if (strlen($password) < 8) throw new RuntimeException('A senha precisa ter pelo menos 8 caracteres.');
            $stmt = db()->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            $_SESSION['notice'] = 'Conta criada. Agora você já pode entrar.';
            redirect('index.php?page=login');
        }
        if ($page === 'property_create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $state = strtoupper(trim((string)($_POST['state'] ?? '')));
            $area = trim((string)($_POST['area_ha'] ?? ''));
            if ($name === '') throw new RuntimeException('Informe o nome da propriedade.');
            if ($area !== '' && (!is_numeric($area) || (float)$area < 0)) throw new RuntimeException('Área inválida.');
            $stmt = db()->prepare('INSERT INTO properties (owner_id,name,city,state,area_ha) VALUES (?,?,?,?,?)');
            $stmt->execute([current_user()['id'], $name, $city ?: null, $state ?: null, $area !== '' ? $area : null]);
            $_SESSION['notice'] = 'Propriedade cadastrada.';
            redirect('index.php?page=properties');
        }
        if ($page === 'field_create') {
            $propertyId = (int)($_POST['property_id'] ?? 0);
            $property = find_accessible_property($propertyId);
            if (!$property || !can_edit_property($property)) throw new RuntimeException('Você não tem permissão para alterar esta propriedade.');
            $name = trim((string)($_POST['name'] ?? ''));
            $area = trim((string)($_POST['area_ha'] ?? ''));
            $crop = trim((string)($_POST['crop_name'] ?? ''));
            if ($name === '') throw new RuntimeException('Informe o nome do talhão.');
            if ($area !== '' && (!is_numeric($area) || (float)$area < 0)) throw new RuntimeException('Área inválida.');
            $stmt = db()->prepare('INSERT INTO fields (property_id,name,area_ha,crop_name) VALUES (?,?,?,?)');
            $stmt->execute([$propertyId, $name, $area !== '' ? $area : null, $crop ?: null]);
            $_SESSION['notice'] = 'Talhão cadastrado.';
            redirect('index.php?page=property&id=' . $propertyId);
        }
        if ($page === 'activity_create') {
            $propertyId = (int)($_POST['property_id'] ?? 0);
            $property = find_accessible_property($propertyId);
            if (!$property || !can_edit_property($property)) throw new RuntimeException('Você não tem permissão para registrar atividades nesta propriedade.');
            $fieldId = (int)($_POST['field_id'] ?? 0);
            if ($fieldId > 0) {
                $check = db()->prepare('SELECT id FROM fields WHERE id=? AND property_id=?');
                $check->execute([$fieldId, $propertyId]);
                if (!$check->fetch()) throw new RuntimeException('Talhão inválido.');
            } else { $fieldId = null; }
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($title === '') throw new RuntimeException('Informe o título da atividade.');
            $stmt = db()->prepare('INSERT INTO activities (property_id,field_id,user_id,title,description,activity_date) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$propertyId, $fieldId, current_user()['id'], $title, $description ?: null, $_POST['activity_date'] ?: date('Y-m-d')]);
            $_SESSION['notice'] = 'Atividade registrada.';
            redirect('index.php?page=property&id=' . $propertyId);
        }
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
            $errors[] = 'Este e-mail já está cadastrado ou existe um registro duplicado.';
        } else {
            $errors[] = $e instanceof PDOException ? 'Não foi possível salvar os dados. Confira os campos e tente novamente.' : $e->getMessage();
        }
        if ($page === 'property_create') {
            $page = 'properties';
        } elseif ($page === 'field_create' || $page === 'activity_create') {
            $_GET['id'] = (string)(int)($_POST['property_id'] ?? 0);
            $page = 'property';
        }
    }
}

$user = current_user();
$properties = [];
if ($user) {
    $properties = user_properties();
}
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Agro') ?> · Germina</title><link rel="stylesheet" href="assets/app.css?v=20261124g">
</head><body>
<?php if (!$user): ?>
<div class="auth-shell"><div class="auth-brand"><span class="brand-mark">✳</span><span>Germina</span></div>
<section class="auth-card">
<?php if ($page === 'register'): ?>
<h1>Crie sua conta</h1><p class="muted">Comece a organizar sua rotina no campo.</p>
<?php foreach ($errors as $error): ?><div class="alert"><?= e($error) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><label>Nome completo<input name="name" required minlength="3" autocomplete="name"></label><label>E-mail<input name="email" type="email" required autocomplete="email"></label><label>Senha<input name="password" type="password" required minlength="8" autocomplete="new-password"></label><label>Perfil<select name="role"><option value="producer">Produtor rural</option><option value="professional">Profissional do agro</option></select></label><button class="btn primary full">Criar conta</button></form>
<p class="auth-foot">Já tem conta? <a href="index.php?page=login">Entrar</a></p>
<?php else: ?>
<h1>Bem-vindo de volta</h1><p class="muted">Entre para acompanhar suas propriedades.</p>
<?php if ($notice): ?><div class="success"><?= e($notice) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= e($error) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><label>E-mail<input name="email" type="email" required autocomplete="email"></label><label>Senha<input name="password" type="password" required autocomplete="current-password"></label><button class="btn primary full">Entrar</button></form>
<p class="auth-foot">Ainda não tem conta? <a href="index.php?page=register">Criar conta</a></p>
<?php endif; ?>
</section><p class="auth-caption">Gestão simples para quem vive o campo.</p></div>
<?php else: ?>
<div class="layout"><aside class="sidebar" id="appSidebar"><a class="brand" href="index.php"><span class="brand-mark">✳</span><span>Germina<small>GESTÃO NO CAMPO</small></span></a><div class="nav-label">MENU PRINCIPAL</div>
<a class="nav-link <?= $page==='dashboard'?'active':'' ?>" href="index.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg></span><span>Visão geral</span></a>
<a class="nav-link <?= $page==='properties' || $page==='property'?'active':'' ?>" href="index.php?page=properties"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-7h6v7"/></svg></span><span>Propriedades</span></a>
<a class="nav-link <?= $page==='activities'?'active':'' ?>" href="index.php?page=activities"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7"/></svg></span><span>Diário de campo</span></a>
<a class="nav-link <?= $page==='team'?'active':'' ?>" href="index.php?page=team"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/></svg></span><span>Equipe e acessos</span></a>
<a class="nav-link <?= $page==='visits'?'active':'' ?>" href="index.php?page=visits"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4m8-4v4M3 9h18m-13 5h3m-3 3h7"/></svg></span><span>Visitas técnicas</span></a>
<a class="nav-link <?= $page==='recommendations'?'active':'' ?>" href="index.php?page=recommendations"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21c-5-3-8-7-8-11a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 4-3 8-8 11Z"/><path d="M12 18c0-5 2-8 5-10M12 14c-1-2-3-3-5-3"/></svg></span><span>Recomendações</span></a>
<a class="nav-link <?= $page==='tasks'?'active':'' ?>" href="index.php?page=tasks"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="m8 12 2.5 2.5L16 9m-8-3h8"/></svg></span><span>Tarefas</span></a>
<a class="nav-link <?= $page==='reports'?'active':'' ?>" href="index.php?page=reports"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V4m0 16h17"/><rect x="7" y="12" width="3" height="5" rx="1"/><rect x="13" y="8" width="3" height="9" rx="1"/><rect x="19" y="5" width="2" height="12" rx="1"/></svg></span><span>Relatórios</span></a>
<a class="nav-link <?= $page==='attachments'?'active':'' ?>" href="index.php?page=attachments"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 12 6.5-6.5a4 4 0 0 1 5.7 5.7l-9 9a6 6 0 0 1-8.5-8.5l9-9"/></svg></span><span>Arquivos</span></a>
<div class="sidebar-bottom"><div class="avatar"><?= e(strtoupper(substr($user['name'],0,1))) ?></div><div class="user-meta"><strong><?= e($user['name']) ?></strong><small><?= $user['role']==='professional'?'Profissional':'Produtor rural' ?></small></div><form method="post" action="index.php?page=logout"><?= csrf_field() ?><button class="logout" title="Sair">↗</button></form></div></aside><button class="sidebar-backdrop" type="button" aria-label="Fechar menu" data-sidebar-close></button>
<main class="main"><header class="topbar"><?php if ($page !== 'dashboard'): ?><button class="mobile-back" type="button" data-mobile-back aria-label="Voltar"><span aria-hidden="true">←</span><span>Voltar</span></button><?php endif; ?><button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="appSidebar" aria-expanded="false" data-sidebar-toggle><span></span><span></span><span></span></button><div><div class="breadcrumb">Workspace / <?= e($page==='dashboard'?'Visão geral':($page==='property'?'Propriedade':($page==='activities'?'Diário de campo':'Propriedades'))) ?></div><h1><?= e($page==='dashboard'?'Visão geral':($page==='property'?'Detalhes da propriedade':($page==='activities'?'Diário de campo':'Propriedades'))) ?></h1></div><div class="top-actions"><span class="user-chip"><?= e($user['name']) ?></span></div></header>
<div class="content">
<?php if ($notice): ?><div class="success"><?= e($notice) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($page==='dashboard'): ?>
<div class="welcome"><div><span class="eyebrow">SEU ESPAÇO DE TRABALHO</span><h2>Olá, <?= e(explode(' ',$user['name'])[0]) ?> <span>🌱</span></h2><p>Acompanhe suas propriedades e organize as atividades no campo.</p></div><a class="btn primary" href="index.php?page=properties">+ Nova propriedade</a></div>
<div class="stats"><article class="stat"><span>Propriedades</span><strong><?= count($properties) ?></strong><small>cadastradas na sua conta</small></article><article class="stat"><span>Talhões</span><strong><?= array_sum(array_map(fn($p)=>(int)$p['field_count'],$properties)) ?></strong><small>em suas propriedades</small></article><article class="stat"><span>Atividades recentes</span><strong><?= count_recent_activities((int)$user['id']) ?></strong><small>últimos 30 dias</small></article></div>
<div class="panel"><div class="panel-head"><div><h3>Minhas propriedades</h3><p>Seus locais de produção cadastrados.</p></div><a href="index.php?page=properties" class="text-link">Ver todas →</a></div>
<?php if (!$properties): ?><div class="empty"><div class="empty-icon">⌂</div><strong>Nenhuma propriedade cadastrada</strong><p>Comece adicionando sua primeira propriedade rural.</p><a class="btn primary" href="index.php?page=properties">Cadastrar propriedade</a></div>
<?php else: ?><div class="property-grid"><?php foreach (array_slice($properties,0,4) as $p): ?><a class="property-card" href="index.php?page=property&id=<?= (int)$p['id'] ?>"><span class="property-symbol">⌂</span><strong><?= e($p['name']) ?></strong><small><?= e(trim(($p['city']??'').' / '.($p['state']??''),' /')) ?: 'Localização não informada' ?></small><span class="property-meta"><?= (int)$p['field_count'] ?> talhão(ões) <b>→</b></span></a><?php endforeach; ?></div><?php endif; ?></div>
<?php elseif ($page==='properties'): ?>
<div class="page-intro"><div><h2>Propriedades rurais</h2><p>Cadastre e acompanhe suas áreas de produção.</p></div></div>
<div class="panel form-panel"><h3>Adicionar propriedade</h3><form method="post" action="index.php?page=property_create" class="form-grid"><?= csrf_field() ?><label class="span-2">Nome da propriedade<input name="name" required placeholder="Ex.: Fazenda Boa Vista"></label><label>Município<input name="city" placeholder="Município"></label><label>UF<input name="state" maxlength="2" placeholder="SP"></label><label>Área total (hectares)<input name="area_ha" type="number" min="0" step="0.01" placeholder="Ex.: 42.5"></label><div class="form-submit"><button class="btn primary">Salvar propriedade</button></div></form></div>
<div class="property-list"><?php foreach ($properties as $p): ?><a class="property-row" href="index.php?page=property&id=<?= (int)$p['id'] ?>"><span class="property-symbol">⌂</span><span class="row-main"><strong><?= e($p['name']) ?></strong><small><?= e(trim(($p['city']??'').' / '.($p['state']??''),' /')) ?: 'Localização não informada' ?></small></span><span class="row-detail"><?= e($p['area_ha']!==null?number_format((float)$p['area_ha'],2,',','.').' ha':'Área não informada') ?></span><span class="row-detail"><?= (int)$p['field_count'] ?> talhões</span><b>→</b></a><?php endforeach; ?></div>
<?php elseif ($page==='property'): ?>
<?php $property=find_accessible_property((int)($_GET['id']??0)); if (!$property): ?><div class="panel empty"><strong>Propriedade não encontrada</strong><a href="index.php?page=properties">Voltar</a></div><?php else:
$st=db()->prepare('SELECT * FROM fields WHERE property_id=? ORDER BY created_at DESC');$st->execute([$property['id']]);$fields=$st->fetchAll();
$st=db()->prepare('SELECT a.*,f.name AS field_name,u.name AS author FROM activities a LEFT JOIN fields f ON f.id=a.field_id JOIN users u ON u.id=a.user_id WHERE a.property_id=? ORDER BY a.activity_date DESC,a.id DESC LIMIT 20');$st->execute([$property['id']]);$activities=$st->fetchAll(); ?>
<div class="welcome"><div><span class="eyebrow">PROPRIEDADE RURAL</span><h2><?= e($property['name']) ?></h2><p><?= e(trim(($property['city']??'').' / '.($property['state']??''),' /')) ?> · <?= e($property['area_ha']!==null?number_format((float)$property['area_ha'],2,',','.').' hectares':'Área não informada') ?></p></div><a class="btn secondary" href="index.php?page=properties">← Voltar</a></div>
<div class="panel form-panel"><h3>Novo talhão</h3><form method="post" action="index.php?page=field_create" class="form-grid"><?= csrf_field() ?><input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>"><label>Nome do talhão<input name="name" required placeholder="Ex.: Talhão 01"></label><label>Área (hectares)<input name="area_ha" type="number" min="0" step="0.01"></label><label>Cultura atual<input name="crop_name" placeholder="Ex.: Café"></label><div class="form-submit"><button class="btn primary">Adicionar talhão</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Talhões</h3><p>Divisões produtivas desta propriedade.</p></div><span class="count-pill"><?= count($fields) ?></span></div><?php if (!$fields): ?><div class="empty compact">Nenhum talhão cadastrado ainda.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Talhão</th><th>Cultura</th><th>Área</th></tr></thead><tbody><?php foreach($fields as $f): ?><tr><td><strong><?= e($f['name']) ?></strong></td><td><?= e($f['crop_name']?:'—') ?></td><td><?= e($f['area_ha']!==null?number_format((float)$f['area_ha'],2,',','.').' ha':'—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<div class="panel form-panel"><h3>Registrar atividade de campo</h3><form method="post" action="index.php?page=activity_create" class="form-grid"><?= csrf_field() ?><input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>"><label>Talhão<select name="field_id"><option value="0">Geral da propriedade</option><?php foreach($fields as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?></option><?php endforeach; ?></select></label><label>Data<input type="date" name="activity_date" value="<?= date('Y-m-d') ?>"></label><label class="span-2">Atividade<input name="title" required placeholder="Ex.: Adubação, monitoramento, irrigação..."></label><label class="span-2">Observações<textarea name="description" rows="3" placeholder="Detalhes do que foi realizado..."></textarea></label><div class="form-submit"><button class="btn primary">Salvar atividade</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Histórico de atividades</h3><p>Registros recentes desta propriedade.</p></div></div><?php if(!$activities): ?><div class="empty compact">Nenhuma atividade registrada.</div><?php else: ?><div class="activity-list"><?php foreach($activities as $a): ?><div class="activity-item"><span class="activity-dot"></span><div><strong><?= e($a['title']) ?></strong><p><?= e($a['description']??'') ?></p><small><?= e($a['field_name']?:'Propriedade') ?> · <?= date('d/m/Y',strtotime($a['activity_date'])) ?> · <?= e($a['author']) ?></small></div></div><?php endforeach; ?></div><?php endif; ?></div>
<?php endif; ?>
<?php elseif ($page==='activities'): ?>
<div class="page-intro"><div><h2>Diário de campo</h2><p>Atividades registradas nas suas propriedades.</p></div></div>
<?php $st=db()->prepare('SELECT a.*,p.name AS property_name,f.name AS field_name FROM activities a JOIN properties p ON p.id=a.property_id LEFT JOIN fields f ON f.id=a.field_id WHERE p.owner_id=? ORDER BY a.activity_date DESC,a.id DESC LIMIT 100');$st->execute([$user['id']]);$allActivities=$st->fetchAll(); ?>
<div class="panel"><?php if(!$allActivities): ?><div class="empty"><strong>Seu diário está vazio</strong><p>Abra uma propriedade para registrar a primeira atividade.</p><a class="btn primary" href="index.php?page=properties">Ver propriedades</a></div><?php else: ?><div class="activity-list"><?php foreach($allActivities as $a): ?><div class="activity-item"><span class="activity-dot"></span><div><strong><?= e($a['title']) ?></strong><p><?= e($a['description']??'') ?></p><small><?= e($a['property_name']) ?> · <?= e($a['field_name']?:'Área geral') ?> · <?= date('d/m/Y',strtotime($a['activity_date'])) ?></small></div></div><?php endforeach; ?></div><?php endif; ?></div>
<?php else: ?><div class="panel empty"><strong>Página não encontrada</strong><a href="index.php">Voltar ao início</a></div><?php endif; ?>
<footer class="footer">Germina · Ambiente de testes · <?= date('Y') ?></footer></div></main></div>
<?php endif; ?>
<script>
(function(){
  const body=document.body;
  document.querySelectorAll('[data-mobile-back]').forEach(button=>button.addEventListener('click',()=>{
    try {
      const previous=new URL(document.referrer);
      if(previous.origin===window.location.origin && window.history.length>1){window.history.back();return;}
    } catch(e) {}
    window.location.href='index.php';
  }));
  const toggle=document.querySelector('[data-sidebar-toggle]');
  const close=document.querySelectorAll('[data-sidebar-close], .sidebar .nav-link');
  const sidebar=document.getElementById('appSidebar');
  if(!toggle||!sidebar)return;
  function setOpen(open){
    body.classList.toggle('sidebar-open',open);
    toggle.setAttribute('aria-expanded',open?'true':'false');
    toggle.setAttribute('aria-label',open?'Fechar menu':'Abrir menu');
  }
  toggle.addEventListener('click',()=>setOpen(!body.classList.contains('sidebar-open')));
  close.forEach(el=>el.addEventListener('click',()=>setOpen(false)));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});
  window.addEventListener('resize',()=>{if(window.innerWidth>850)setOpen(false)});
})();
</script>
</body></html>
