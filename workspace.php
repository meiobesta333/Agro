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
  if($action==='consultation_send'){
   $pid=(int)($_POST['property_id']??0);module_require_property($pid,false);
   $message=trim((string)($_POST['message']??''));
   if(mb_strlen($message)>5000)throw new RuntimeException('A mensagem deve ter no máximo 5.000 caracteres.');
   $stored=null;$original=null;$mime=null;$size=0;
   if(isset($_FILES['attachment'])&&$_FILES['attachment']['error']!==UPLOAD_ERR_NO_FILE){
    $f=$_FILES['attachment'];
    if($f['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Não foi possível receber o arquivo. Verifique o limite de envio da hospedagem.');
    if((int)$f['size']>20*1024*1024)throw new RuntimeException('O arquivo deve ter no máximo 20 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf','text/plain'=>'txt','text/csv'=>'csv','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/aac'=>'aac','audio/webm'=>'weba','video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'];
    if(!isset($allowed[$mime]))throw new RuntimeException('Formato não permitido. Envie imagem, PDF, documento, áudio MP3/OGG/WAV/M4A ou vídeo MP4/WEBM/MOV.');
    $dir=__DIR__.'/storage';if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('Não foi possível preparar o armazenamento.');
    $stored='consulta_'.bin2hex(random_bytes(18)).'.'.$allowed[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$stored))throw new RuntimeException('Falha ao salvar o arquivo enviado.');
    $original=mb_substr(basename((string)$f['name']),0,250);$size=(int)$f['size'];
   }
   if($message===''&&$stored===null)throw new RuntimeException('Escreva uma mensagem ou selecione um arquivo.');
   try{$st=db()->prepare('INSERT INTO consultation_messages(property_id,sender_id,message_text,original_name,stored_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?)');$st->execute([$pid,$user['id'],$message?:null,$original,$stored,$mime,$size]);}
   catch(Throwable $e){if($stored&&is_file(__DIR__.'/storage/'.$stored))@unlink(__DIR__.'/storage/'.$stored);throw $e;}
   redirect('index.php?page=consultations&id='.$pid.'#latest-message');
  }
  if($action==='equipment_create'){
   $pid=(int)($_POST['property_id']??0);module_require_property($pid,true);
   $kind=$_POST['kind']??'vehicle';if(!in_array($kind,['vehicle','implement','tool','machine'],true))$kind='machine';
   $name=trim((string)($_POST['name']??''));if($name==='')throw new RuntimeException('Informe o nome do veículo ou equipamento.');
   $st=db()->prepare('INSERT INTO farm_equipment(property_id,kind,name,category,brand,model,identifier,year_manufactured,hour_meter,status,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
   $st->execute([$pid,$kind,$name,trim((string)($_POST['category']??''))?:null,trim((string)($_POST['brand']??''))?:null,trim((string)($_POST['model']??''))?:null,trim((string)($_POST['identifier']??''))?:null,(int)($_POST['year_manufactured']??0)?:null,(float)($_POST['hour_meter']??0),in_array($_POST['status']??'',['active','maintenance','inactive'],true)?$_POST['status']:'active',trim((string)($_POST['notes']??''))?:null,$user['id']]);
   flash('Veículo ou equipamento cadastrado.');redirect('index.php?page=activities&tab=equipment');
  }
  if($action==='stock_create'){
   $pid=(int)($_POST['property_id']??0);module_require_property($pid,true);
   $name=trim((string)($_POST['name']??''));$unit=trim((string)($_POST['unit']??''));if($name===''||$unit==='')throw new RuntimeException('Informe o nome do insumo e sua unidade.');
   $qty=(float)($_POST['quantity']??0);$min=(float)($_POST['minimum_quantity']??0);if($qty<0||$min<0)throw new RuntimeException('As quantidades não podem ser negativas.');
   $cat=$_POST['category']??'other';if(!in_array($cat,['seed','fertilizer','corrective','herbicide','fungicide','insecticide','defensive','veterinary','fuel','lubricant','other'],true))$cat='other';
   $pdo=db();$pdo->beginTransaction();try{$st=$pdo->prepare('INSERT INTO farm_stock_items(property_id,name,category,brand,unit,quantity,minimum_quantity,unit_cost,storage_location,expiry_date,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$st->execute([$pid,$name,$cat,trim((string)($_POST['brand']??''))?:null,$unit,$qty,$min,(float)($_POST['unit_cost']??0),trim((string)($_POST['storage_location']??''))?:null,$_POST['expiry_date']?:null,$user['id']]);$item=(int)$pdo->lastInsertId();if($qty>0){$mv=$pdo->prepare("INSERT INTO farm_stock_movements(item_id,property_id,user_id,movement_type,quantity,unit_cost,movement_date,notes) VALUES(?,?,?,'in',?,?,CURDATE(),'Saldo inicial')");$mv->execute([$item,$pid,$user['id'],$qty,(float)($_POST['unit_cost']??0)]);}$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
   flash('Insumo cadastrado no estoque.');redirect('index.php?page=activities&tab=stock');
  }
  if($action==='stock_move'){
   $itemId=(int)($_POST['item_id']??0);$st=db()->prepare('SELECT * FROM farm_stock_items WHERE id=?');$st->execute([$itemId]);$item=$st->fetch();if(!$item)throw new RuntimeException('Insumo não encontrado.');module_require_property((int)$item['property_id'],true);
   $type=$_POST['movement_type']??'out';if(!in_array($type,['in','out','adjustment'],true))$type='out';$qty=(float)($_POST['quantity']??0);if($qty<=0)throw new RuntimeException('Informe uma quantidade maior que zero.');
   $pdo=db();$pdo->beginTransaction();try{$lock=$pdo->prepare('SELECT quantity FROM farm_stock_items WHERE id=? FOR UPDATE');$lock->execute([$itemId]);$current=(float)$lock->fetchColumn();$delta=$type==='in'?$qty:($type==='out'?-$qty:$qty-$current);if($current+$delta<0)throw new RuntimeException('Estoque insuficiente para essa saída.');$pdo->prepare('UPDATE farm_stock_items SET quantity=quantity+? WHERE id=?')->execute([$delta,$itemId]);$pdo->prepare('INSERT INTO farm_stock_movements(item_id,property_id,user_id,movement_type,quantity,unit_cost,movement_date,notes) VALUES(?,?,?,?,?,?,?,?)')->execute([$itemId,$item['property_id'],$user['id'],$type,$qty,(float)($_POST['unit_cost']??$item['unit_cost']),$_POST['movement_date']?:date('Y-m-d'),trim((string)($_POST['notes']??''))?:null]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
   flash('Movimentação de estoque registrada.');redirect('index.php?page=activities&tab=stock');
  }
  if($action==='equipment_log'){
   $eid=(int)($_POST['equipment_id']??0);$st=db()->prepare('SELECT * FROM farm_equipment WHERE id=?');$st->execute([$eid]);$eq=$st->fetch();if(!$eq)throw new RuntimeException('Equipamento não encontrado.');module_require_property((int)$eq['property_id'],true);
   $desc=trim((string)($_POST['description']??''));if($desc==='')throw new RuntimeException('Descreva a operação realizada.');
   $st=db()->prepare('INSERT INTO farm_equipment_logs(equipment_id,property_id,field_id,user_id,operation_date,operation,area_ha,hours_used,fuel_liters,description) VALUES(?,?,?,?,?,?,?,?,?,?)');
   $fid=(int)($_POST['field_id']??0)?:null;if($fid){$check=db()->prepare('SELECT id FROM fields WHERE id=? AND property_id=?');$check->execute([$fid,$eq['property_id']]);if(!$check->fetch())throw new RuntimeException('Talhão inválido para esta propriedade.');}
   $st->execute([$eid,$eq['property_id'],$fid,$user['id'],$_POST['operation_date']?:date('Y-m-d'),trim((string)($_POST['operation']??'Operação')), (float)($_POST['area_ha']??0)?:null,(float)($_POST['hours_used']??0)?:null,(float)($_POST['fuel_liters']??0)?:null,$desc]);
   flash('Uso do equipamento registrado.');redirect('index.php?page=activities&tab=operations');
  }
  if($action==='maintenance_create'){
   $eid=(int)($_POST['equipment_id']??0);$st=db()->prepare('SELECT * FROM farm_equipment WHERE id=?');$st->execute([$eid]);$eq=$st->fetch();if(!$eq)throw new RuntimeException('Equipamento não encontrado.');module_require_property((int)$eq['property_id'],true);
   $desc=trim((string)($_POST['description']??''));if($desc==='')throw new RuntimeException('Descreva a manutenção realizada.');
   $st=db()->prepare('INSERT INTO farm_maintenance(equipment_id,property_id,user_id,maintenance_date,maintenance_type,description,cost,next_due_date,provider) VALUES(?,?,?,?,?,?,?,?,?)');
   $st->execute([$eid,$eq['property_id'],$user['id'],$_POST['maintenance_date']?:date('Y-m-d'),in_array($_POST['maintenance_type']??'',['preventive','corrective'],true)?$_POST['maintenance_type']:'preventive',$desc,(float)($_POST['cost']??0),$_POST['next_due_date']?:null,trim((string)($_POST['provider']??''))?:null]);
   flash('Manutenção registrada.');redirect('index.php?page=activities&tab=maintenance');
  }
  if($action==='generate_access_code'){
   $pid=(int)($_POST['property_id']??0);$p=module_require_property($pid,true);
   if((int)$p['owner_id']!==(int)$user['id'])throw new RuntimeException('Somente o produtor responsável pode gerar um código.');
   $code=strtoupper(bin2hex(random_bytes(16)));
   $pdo=db();$pdo->beginTransaction();
   try{
    $pdo->prepare("UPDATE property_access_requests r JOIN property_access_codes c ON c.id=r.access_code_id SET r.status='rejected',r.decided_by=?,r.decided_at=NOW(),r.decision_note='Código renovado pelo produtor' WHERE c.property_id=? AND c.status='pending' AND r.status='pending'")->execute([$user['id'],$pid]);
    $pdo->prepare("UPDATE property_access_codes SET status='revoked',revoked_at=NOW() WHERE property_id=? AND status IN ('active','pending')")->execute([$pid]);
    $st=$pdo->prepare("INSERT INTO property_access_codes(property_id,code_hash,status,created_by,expires_at) VALUES(?,?,'active',?,DATE_ADD(NOW(),INTERVAL 7 DAY))");
    $st->execute([$pid,hash('sha256',strtolower($code)),$user['id']]);
    $pdo->commit();
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
   $_SESSION['new_access_code']=['property_id'=>$pid,'code'=>$code];
   flash('Código gerado. Copie-o agora e envie ao profissional.');redirect('index.php?page=team');
  }
  if($action==='request_access'){
   if($user['role']!=='professional')throw new RuntimeException('Somente contas de profissional podem solicitar acesso por código.');
   $code=strtolower(preg_replace('/[\\s-]+/','',(string)($_POST['access_code']??'')));
   if(!preg_match('/^[a-f0-9]{32}$/',$code))throw new RuntimeException('Código inválido. Confira os 32 caracteres informados.');
   $pdo=db();$pdo->beginTransaction();
   try{
    $st=$pdo->prepare("SELECT c.id,c.property_id,c.status,c.expires_at,p.owner_id FROM property_access_codes c JOIN properties p ON p.id=c.property_id WHERE c.code_hash=? FOR UPDATE");
    $st->execute([hash('sha256',$code)]);$accessCode=$st->fetch();
    if(!$accessCode||$accessCode['status']!=='active'||strtotime($accessCode['expires_at'])<=time())throw new RuntimeException('Código inválido, expirado ou já utilizado.');
    if((int)$accessCode['owner_id']===(int)$user['id'])throw new RuntimeException('Você não pode solicitar acesso à sua própria propriedade.');
    $st=$pdo->prepare('SELECT id FROM property_members WHERE property_id=? AND user_id=?');$st->execute([$accessCode['property_id'],$user['id']]);
    if($st->fetch())throw new RuntimeException('Você já tem acesso a esta propriedade.');
    $st=$pdo->prepare("SELECT id FROM property_access_requests WHERE property_id=? AND requested_by=? AND status='pending'");$st->execute([$accessCode['property_id'],$user['id']]);
    if($st->fetch())throw new RuntimeException('Você já possui uma solicitação pendente para esta propriedade.');
    $st=$pdo->prepare("INSERT INTO property_access_requests(property_id,access_code_id,requested_by,status) VALUES(?,?,?,'pending')");
    $st->execute([$accessCode['property_id'],$accessCode['id'],$user['id']]);
    $pdo->prepare("UPDATE property_access_codes SET status='pending' WHERE id=? AND status='active'")->execute([$accessCode['id']]);
    $pdo->commit();
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
   flash('Solicitação enviada. O produtor precisa aprovar seu acesso.');redirect('index.php?page=team');
  }
  if($action==='approve_access'){
   $requestId=(int)($_POST['request_id']??0);$permission=in_array($_POST['permission']??'',['view','edit'],true)?$_POST['permission']:'view';
   $pdo=db();$pdo->beginTransaction();
   try{
    $st=$pdo->prepare("SELECT r.id,r.property_id,r.access_code_id,r.requested_by,r.status,c.status code_status,p.owner_id FROM property_access_requests r JOIN property_access_codes c ON c.id=r.access_code_id JOIN properties p ON p.id=r.property_id WHERE r.id=? FOR UPDATE");
    $st->execute([$requestId]);$request=$st->fetch();
    if(!$request||$request['status']!=='pending'||$request['code_status']!=='pending'||(int)$request['owner_id']!==(int)$user['id'])throw new RuntimeException('Solicitação não encontrada ou não está mais pendente.');
    $st=$pdo->prepare("INSERT INTO property_members(property_id,user_id,permission,invited_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission)");
    $st->execute([$request['property_id'],$request['requested_by'],$permission,$user['id']]);
    $pdo->prepare("UPDATE property_access_requests SET status='approved',decided_by=?,decided_at=NOW() WHERE id=?")->execute([$user['id'],$requestId]);
    $pdo->prepare("UPDATE property_access_codes SET status='used',used_at=NOW() WHERE id=?")->execute([$request['access_code_id']]);
    $pdo->commit();
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
   flash('Acesso aprovado. O profissional já pode acessar a propriedade.');redirect('index.php?page=team');
  }
  if($action==='reject_access'){
   $requestId=(int)($_POST['request_id']??0);$pdo=db();$pdo->beginTransaction();
   try{
    $st=$pdo->prepare("SELECT r.id,r.access_code_id,r.status,c.expires_at,p.owner_id FROM property_access_requests r JOIN property_access_codes c ON c.id=r.access_code_id JOIN properties p ON p.id=r.property_id WHERE r.id=? FOR UPDATE");
    $st->execute([$requestId]);$request=$st->fetch();
    if(!$request||$request['status']!=='pending'||(int)$request['owner_id']!==(int)$user['id'])throw new RuntimeException('Solicitação não encontrada ou não está mais pendente.');
    $pdo->prepare("UPDATE property_access_requests SET status='rejected',decided_by=?,decided_at=NOW() WHERE id=?")->execute([$user['id'],$requestId]);
    $pdo->prepare("UPDATE property_access_codes SET status=IF(expires_at>NOW(),'active','revoked') WHERE id=? AND status='pending'")->execute([$request['access_code_id']]);
    $pdo->commit();
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
   flash('Solicitação rejeitada.');redirect('index.php?page=team');
  }
  if($action==='remove_member'){
   $pid=(int)($_POST['property_id']??0);$p=module_require_property($pid,true);
   if((int)$p['owner_id']!==(int)$user['id'])throw new RuntimeException('Somente o produtor responsável pode remover membros.');
   $memberId=(int)($_POST['member_id']??0);
   $st=db()->prepare('DELETE FROM property_members WHERE property_id=? AND user_id=?');$st->execute([$pid,$memberId]);
   flash('Acesso do profissional revogado.');redirect('index.php?page=team');
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
$newAccessCode=$_SESSION['new_access_code']??null;unset($_SESSION['new_access_code']);
$incomingRequests=[];$myAccessRequests=[];$activeAccessCodes=[];
if($page==='team'){
 if($user['role']==='professional'){
  $st=db()->prepare("SELECT r.id,r.status,r.requested_at,p.name property_name,u.name owner_name FROM property_access_requests r JOIN properties p ON p.id=r.property_id JOIN users u ON u.id=p.owner_id WHERE r.requested_by=? ORDER BY r.requested_at DESC LIMIT 30");
  $st->execute([$user['id']]);$myAccessRequests=$st->fetchAll();
 }else{
  $ownerIds=array_values(array_map(fn($p)=>(int)$p['id'],array_filter($props,fn($p)=>(int)$p['owner_id']===(int)$user['id'])));
  if($ownerIds){
   $ownerSql=implode(',',array_fill(0,count($ownerIds),'?'));
   $st=db()->prepare("SELECT r.id,r.property_id,r.requested_at,p.name property_name,u.name professional_name,u.email professional_email FROM property_access_requests r JOIN properties p ON p.id=r.property_id JOIN users u ON u.id=r.requested_by WHERE r.property_id IN ($ownerSql) AND p.owner_id=? AND r.status='pending' ORDER BY r.requested_at ASC");
   $st->execute([...$ownerIds,$user['id']]);$incomingRequests=$st->fetchAll();
   $st=db()->prepare("SELECT id,property_id,status,expires_at FROM property_access_codes WHERE property_id IN ($ownerSql) AND status IN ('active','pending') AND expires_at>NOW() ORDER BY created_at DESC");
   $st->execute($ownerIds);$activeAccessCodes=$st->fetchAll();
  }
 }
}
$idsSql=$ids?implode(',',array_fill(0,count($ids),'?')):'0';
function module_rows(string $sql,array $ids): array {
 if(!$ids)return [];
 try{$st=db()->prepare($sql);$st->execute($ids);return $st->fetchAll();}
 catch(Throwable $e){error_log('Germina workspace query failed: '.$e->getMessage());return [];}
}
$activities=[];$visits=[];$recs=[];$tasks=[];$equipment=[];$stock=[];$equipmentLogs=[];$maintenance=[];$fields=[];
$consultationProperty=null;$consultationMessages=[];$consultationError='';
if($page==='consultations'){
 if($props){
  $requestedId=(int)($_GET['id']??$props[0]['id']);
  $consultationProperty=module_property($requestedId);
  if(!$consultationProperty){$consultationProperty=module_property((int)$props[0]['id']);}
  if($consultationProperty){
   try{
    $st=db()->prepare('SELECT m.*,u.name sender_name FROM consultation_messages m JOIN users u ON u.id=m.sender_id WHERE m.property_id=? ORDER BY m.id DESC LIMIT 100');
    $st->execute([$consultationProperty['id']]);$consultationMessages=array_reverse($st->fetchAll());
   }catch(Throwable $e){error_log('Germina consultation history: '.$e->getMessage());$consultationError='O recurso de Consultas ainda precisa ser preparado no banco de dados. Execute upgrade_consultas.php uma única vez.';}
  }
 }
}
$needsActivities=in_array($page,['activities','reports'],true);
$needsVisits=in_array($page,['visits','reports'],true);
$needsRecs=$page==='recommendations';
$needsTasks=in_array($page,['dashboard','tasks'],true);
$needsDiary=$page==='activities';
$needsFields=$page==='dashboard'||($page==='activities'&&($_GET['tab']??'overview')==='operations');
if($ids&&$needsActivities)$activities=module_rows("SELECT a.*,p.name property_name FROM activities a JOIN properties p ON p.id=a.property_id WHERE a.property_id IN ($idsSql) ORDER BY a.activity_date DESC LIMIT 100",$ids);
if($ids&&$needsVisits)$visits=module_rows("SELECT v.*,p.name property_name,u.name professional_name FROM visits v JOIN properties p ON p.id=v.property_id JOIN users u ON u.id=v.professional_id WHERE v.property_id IN ($idsSql) ORDER BY v.visit_date DESC LIMIT 100",$ids);
if($ids&&$needsRecs)$recs=module_rows("SELECT r.*,p.name property_name,u.name author_name FROM recommendations r JOIN properties p ON p.id=r.property_id JOIN users u ON u.id=r.author_id WHERE r.property_id IN ($idsSql) ORDER BY r.created_at DESC LIMIT 100",$ids);
if($ids&&$needsTasks)$tasks=module_rows("SELECT t.*,p.name property_name,u.name assignee_name FROM tasks t JOIN properties p ON p.id=t.property_id LEFT JOIN users u ON u.id=t.assigned_to WHERE t.property_id IN ($idsSql) ORDER BY t.due_date IS NULL,t.due_date,t.created_at DESC LIMIT 100",$ids);
if($ids&&$needsDiary){
 $equipment=module_rows("SELECT e.*,p.name property_name FROM farm_equipment e JOIN properties p ON p.id=e.property_id WHERE e.property_id IN ($idsSql) ORDER BY e.name",$ids);
 $stock=module_rows("SELECT s.*,p.name property_name FROM farm_stock_items s JOIN properties p ON p.id=s.property_id WHERE s.property_id IN ($idsSql) ORDER BY s.name",$ids);
 $equipmentLogs=module_rows("SELECT l.*,e.name equipment_name,p.name property_name FROM farm_equipment_logs l JOIN farm_equipment e ON e.id=l.equipment_id JOIN properties p ON p.id=l.property_id WHERE l.property_id IN ($idsSql) ORDER BY l.operation_date DESC,l.id DESC LIMIT 100",$ids);
 $maintenance=module_rows("SELECT m.*,e.name equipment_name,p.name property_name FROM farm_maintenance m JOIN farm_equipment e ON e.id=m.equipment_id JOIN properties p ON p.id=m.property_id WHERE m.property_id IN ($idsSql) ORDER BY m.maintenance_date DESC,m.id DESC LIMIT 100",$ids);
}
if($needsFields){try{foreach($props as $p){$st=db()->prepare('SELECT id,name FROM fields WHERE property_id=?');$st->execute([$p['id']]);foreach($st->fetchAll() as $f)$fields[]=$f+['property_id'=>$p['id']];}}catch(Throwable $e){error_log('Germina workspace fields query failed: '.$e->getMessage());}}
$titleMap=['dashboard'=>'Visão geral','team'=>'Equipe e acessos','visits'=>'Visitas técnicas','recommendations'=>'Recomendações','tasks'=>'Tarefas','reports'=>'Relatórios','attachments'=>'Arquivos','activities'=>'Diário de campo','consultations'=>'Consultas','properties'=>'Propriedades'];
$title=$titleMap[$page]??'Workspace';
$moduleUi=[
 'team'=>['kicker'=>'CONEXÃO E COLABORAÇÃO','title'=>'Sua rede de trabalho','description'=>'Compartilhe propriedades com profissionais e controle o nível de acesso de cada pessoa.','metric'=>'Propriedades disponíveis','value'=>count($props),'icon'=>'team'],
 'visits'=>['kicker'=>'ACOMPANHAMENTO TÉCNICO','title'=>'Presença no campo, decisões melhores','description'=>'Organize os atendimentos e mantenha o histórico técnico de cada propriedade.','metric'=>'Visitas registradas','value'=>count($visits),'icon'=>'visits'],
 'recommendations'=>['kicker'=>'ORIENTAÇÃO AGRONÔMICA','title'=>'Conhecimento que vira produtividade','description'=>'Acompanhe orientações técnicas, prioridades e próximos passos da produção.','metric'=>'Recomendações','value'=>count($recs),'icon'=>'recommendations'],
 'tasks'=>['kicker'=>'PLANEJAMENTO E EXECUÇÃO','title'=>'Da programação à conclusão','description'=>'Distribua responsabilidades, acompanhe prazos e mantenha o trabalho em movimento.','metric'=>'Tarefas cadastradas','value'=>count($tasks),'icon'=>'tasks'],
 'reports'=>['kicker'=>'INTELIGÊNCIA DA OPERAÇÃO','title'=>'Uma visão clara da sua operação','description'=>'Consulte indicadores consolidados e gere uma versão para impressão ou PDF.','metric'=>'Atividades registradas','value'=>count($activities),'icon'=>'reports'],
 'consultations'=>['kicker'=>'COMUNICAÇÃO DIRETA','title'=>'Converse com quem cuida da propriedade','description'=>'Troque mensagens, fotos, documentos, áudios e vídeos com os participantes autorizados.','metric'=>'Propriedades com acesso','value'=>count($props),'icon'=>'consultations'],
 'attachments'=>['kicker'=>'DOCUMENTOS DA PROPRIEDADE','title'=>'Tudo organizado em um só lugar','description'=>'Centralize documentos, laudos e imagens vinculados às suas propriedades.','metric'=>'Propriedades com arquivos','value'=>count($props),'icon'=>'attachments'],
 'activities'=>['kicker'=>'ROTINA E HISTÓRICO','title'=>'A memória do trabalho no campo','description'=>'Consulte os registros realizados e acompanhe o histórico das atividades.','metric'=>'Registros recentes','value'=>count($activities),'icon'=>'activities']
];
$moduleIconSvg=[
 'team'=>'<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/></svg>',
 'visits'=>'<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4m8-4v4M3 9h18m-13 5h3m-3 3h7"/></svg>',
 'recommendations'=>'<svg viewBox="0 0 24 24"><path d="M12 21c-5-3-8-7-8-11a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 4-3 8-8 11Z"/><path d="M12 18c0-5 2-8 5-10"/></svg>',
 'tasks'=>'<svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="m8 12 2.5 2.5L16 9"/></svg>',
 'reports'=>'<svg viewBox="0 0 24 24"><path d="M4 20V4m0 16h17"/><rect x="7" y="12" width="3" height="5" rx="1"/><rect x="13" y="8" width="3" height="9" rx="1"/><rect x="19" y="5" width="2" height="12" rx="1"/></svg>',
 'attachments'=>'<svg viewBox="0 0 24 24"><path d="m8 12 6.5-6.5a4 4 0 0 1 5.7 5.7l-9 9a6 6 0 0 1-8.5-8.5l9-9"/></svg>',
 'consultations'=>'<svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5a9 9 0 0 1-.9-4A8.5 8.5 0 0 1 12.5 3H13a8.5 8.5 0 0 1 8 8v.5Z"/><path d="M8 12h8M8 8.5h5"/></svg>',
 'activities'=>'<svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7"/></svg>'
];
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> · Germina</title><link rel="stylesheet" href="assets/app.css?v=20261124h"></head><body>
<div class="layout"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">✳</span><span>Germina<small>GESTÃO NO CAMPO</small></span></a><div class="nav-label">MENU PRINCIPAL</div>
<?php $navIcons=['dashboard'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/></svg>','properties'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-7h6v7"/></svg>','activities'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7"/></svg>','team'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/></svg>','visits'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4m8-4v4M3 9h18m-13 5h3m-3 3h7"/></svg>','recommendations'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21c-5-3-8-7-8-11a4 4 0 0 1 8-1 4 4 0 0 1 8 1c0 4-3 8-8 11Z"/><path d="M12 18c0-5 2-8 5-10M12 14c-1-2-3-3-5-3"/></svg>','tasks'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="m8 12 2.5 2.5L16 9m-8-3h8"/></svg>','reports'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V4m0 16h17"/><rect x="7" y="12" width="3" height="5" rx="1"/><rect x="13" y="8" width="3" height="9" rx="1"/><rect x="19" y="5" width="2" height="12" rx="1"/></svg>','attachments'=> '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 12 6.5-6.5a4 4 0 0 1 5.7 5.7l-9 9a6 6 0 0 1-8.5-8.5l9-9"/></svg>','consultations'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5a9 9 0 0 1-.9-4A8.5 8.5 0 0 1 12.5 3H13a8.5 8.5 0 0 1 8 8v.5Z"/><path d="M8 12h8M8 8.5h5"/></svg>']; foreach(['dashboard'=>'Visão geral','properties'=>'Propriedades','activities'=>'Diário de campo','team'=>'Equipe e acessos','visits'=>'Visitas técnicas','recommendations'=>'Recomendações','tasks'=>'Tarefas','reports'=>'Relatórios','attachments'=>'Arquivos'] as $key=>$label):?><a class="nav-link <?= $page===$key?'active':''?>" href="index.php<?= $key==='dashboard'?'':'?page='.$key?>"><span class="nav-icon"><?=$navIcons[$key]??''?></span><span><?=e($label)?></span></a><?php endforeach;?>
<div class="sidebar-bottom"><div class="avatar"><?=e(strtoupper(substr($user['name'],0,1)))?></div><div class="user-meta"><strong><?=e($user['name'])?></strong><small><?=$user['role']==='professional'?'Profissional do agro':'Produtor rural'?></small></div><form method="post" action="index.php?page=logout"><?=csrf_field()?><button class="logout">↗</button></form></div></aside>
<main class="main"><header class="topbar"><button class="mobile-back" type="button" data-mobile-back aria-label="Voltar"><span aria-hidden="true">←</span><span>Voltar</span></button><div><div class="breadcrumb">Workspace / <?=e($title)?></div><h1><?=e($title)?></h1></div><div class="top-actions"><span class="user-chip"><?=e($user['name'])?></span></div></header><div class="content module-page module-<?=e($page)?>">
<?php if($notice):?><div class="success"><?=e($notice)?></div><?php endif;?><?php foreach($errors as $err):?><div class="alert"><?=e($err)?></div><?php endforeach;?>
<?php if(isset($moduleUi[$page])):$ui=$moduleUi[$page];?><section class="module-hero"><div class="module-hero-copy"><span class="module-kicker"><?=e($ui['kicker'])?></span><h2><?=e($ui['title'])?></h2><p><?=e($ui['description'])?></p><div class="module-hero-meta"><span class="module-hero-icon"><?=$moduleIconSvg[$ui['icon']]?></span><span><strong><?=e((string)$ui['value'])?></strong><small><?=e($ui['metric'])?></small></span></div></div><div class="module-hero-art" aria-hidden="true"><span class="art-sun"></span><span class="art-hill art-hill-one"></span><span class="art-hill art-hill-two"></span><span class="art-leaf art-leaf-one"></span><span class="art-leaf art-leaf-two"></span></div></section><?php endif;?>
<?php if($page==='dashboard'):?>
<div class="welcome"><div><span class="eyebrow">SEU ESPAÇO DE TRABALHO</span><h2>Olá, <?=e(explode(' ',$user['name'])[0])?> 🌱</h2><p>Gestão integrada de propriedades, equipe e rotina no campo.</p></div><a class="btn primary" href="index.php?page=properties">+ Nova propriedade</a></div>
<div class="stats"><article class="stat"><span>Propriedades acessíveis</span><strong><?=count($props)?></strong><small>próprias e compartilhadas</small></article><article class="stat"><span>Talhões</span><strong><?=count($fields)?></strong><small>nas propriedades acessíveis</small></article><article class="stat"><span>Tarefas em aberto</span><strong><?=count(array_filter($tasks,fn($t)=>in_array($t['status'],['open','doing'],true)))?></strong><small>pendentes ou em andamento</small></article></div>
<div class="module-grid"><?php $dashboardModules=['team'=>['Equipe e acessos','Convide profissionais e organize permissões.','team'],'visits'=>['Visitas técnicas','Histórico de atendimentos e acompanhamento.','visits'],'recommendations'=>['Recomendações','Orientações e prioridades técnicas.','recommendations'],'tasks'=>['Tarefas','Planeje atividades e acompanhe entregas.','tasks'],'reports'=>['Relatórios','Indicadores e visão consolidada.','reports'],'attachments'=>['Arquivos','Documentos e imagens das propriedades.','attachments'],'consultations'=>['Consultas','Converse com produtores e profissionais, compartilhe mídias e documentos.','consultations']];foreach($dashboardModules as $k=>$m):?><a class="module-card module-card-<?=$k?>" href="index.php?page=<?=$k?>"><span class="module-card-icon"><?=$moduleIconSvg[$m[2]]?></span><span class="module-card-copy"><strong><?=e($m[0])?></strong><small><?=e($m[1])?></small><b>Explorar módulo <span>↗</span></b></span></a><?php endforeach;?></div>
<?php elseif($page==='team'):?>
<div class="page-intro"><h2>Conexões entre produtores e profissionais</h2><p>O produtor compartilha um código; o profissional solicita acesso e aguarda aprovação. Nenhum dado é liberado antes da aprovação.</p></div>
<?php if($user['role']==='professional'):?>
<div class="panel form-panel"><div class="panel-head"><div><h3>Adicionar propriedade à carteira</h3><p>Informe o código de convite recebido do produtor. O código tem validade limitada.</p></div></div><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="request_access"><label class="span-2">Código de acesso<input name="access_code" required minlength="32" maxlength="35" autocomplete="off" placeholder="Ex.: A1B2C3D4E5F60789A1B2C3D4E5F60789"></label><div class="form-submit"><button class="btn primary">Solicitar acesso</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Minhas solicitações</h3><p>Acompanhe os pedidos enviados aos produtores.</p></div></div><?php if(!$myAccessRequests):?><div class="empty">Você ainda não enviou solicitações.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Propriedade</th><th>Produtor</th><th>Solicitada em</th><th>Status</th></tr></thead><tbody><?php foreach($myAccessRequests as $r):?><tr><td><?=e($r['property_name'])?></td><td><?=e($r['owner_name'])?></td><td><?=date('d/m/Y H:i',strtotime($r['requested_at']))?></td><td><?=e(['pending'=>'Aguardando aprovação','approved'=>'Aprovada','rejected'=>'Rejeitada'][$r['status']]??$r['status'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<div class="panel"><div class="panel-head"><div><h3>Minha carteira</h3><p>Propriedades às quais sua conta já tem acesso aprovado.</p></div></div><?php $sharedProps=array_values(array_filter($props,fn($p)=>(int)$p['owner_id']!==(int)$user['id']));?><?php if(!$sharedProps):?><div class="empty">Nenhuma propriedade compartilhada com você ainda.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Propriedade</th><th>Município</th><th>Permissão</th></tr></thead><tbody><?php foreach($sharedProps as $sp):?><tr><td><?=e($sp['name'])?></td><td><?=e(trim(($sp['city']??'').' / '.($sp['state']??''),' /'))?></td><td><?=e(['view'=>'Visualizar','edit'=>'Registrar e editar','manage'=>'Gerenciar'][$sp['permission']??'view']??'Visualizar')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php else:?>
<?php if(!$props):?><div class="panel empty">Cadastre uma propriedade para gerar um código e conectar um profissional.</div><?php endif;?>
<?php foreach($props as $p):if((int)$p['owner_id']!==(int)$user['id'])continue;?>
<div class="panel"><div class="panel-head"><div><h3><?=e($p['name'])?></h3><p>Compartilhamento controlado pelo produtor responsável.</p></div></div>
<?php if($newAccessCode && (int)$newAccessCode['property_id']===(int)$p['id']):?><div class="success"><strong>Novo código — copie agora:</strong><p><code id="newAccessCode"><?=e($newAccessCode['code'])?></code> <button type="button" class="btn secondary" data-copy-code>Copiar código</button></p><small>Por segurança, o código não será exibido novamente. Validade: 7 dias. A aprovação continua obrigatória.</small></div><?php endif;?>
<?php $codeInfo=array_values(array_filter($activeAccessCodes,fn($ac)=>(int)$ac['property_id']===(int)$p['id']));?><div class="form-grid"><div><strong>Código de convite</strong><p class="muted"><?= $codeInfo?'Existe um código '.($codeInfo[0]['status']==='pending'?'aguardando decisão':'ativo').' até '.date('d/m/Y H:i',strtotime($codeInfo[0]['expires_at'])):'Nenhum código ativo no momento.'?></p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="generate_access_code"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><button class="btn primary"><?= $codeInfo?'Gerar novo código':'Gerar código de convite'?></button><small>Gerar outro código revoga o anterior e cancela uma solicitação que ainda esteja pendente.</small></form></div>
<?php $propertyRequests=array_values(array_filter($incomingRequests,fn($r)=>(int)$r['property_id']===(int)$p['id']));?><h4>Solicitações pendentes</h4><?php if(!$propertyRequests):?><div class="empty compact">Nenhuma solicitação aguardando aprovação.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Profissional</th><th>Solicitado em</th><th>Permissão ao aprovar</th><th>Ações</th></tr></thead><tbody><?php foreach($propertyRequests as $req):?><tr><td><?=e($req['professional_name'])?><br><small><?=e($req['professional_email'])?></small></td><td><?=date('d/m/Y H:i',strtotime($req['requested_at']))?></td><td><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="approve_access"><input type="hidden" name="request_id" value="<?=(int)$req['id']?>"><select name="permission"><option value="view">Somente visualizar</option><option value="edit">Visualizar e registrar</option></select><button class="btn primary">Aprovar</button></form></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="reject_access"><input type="hidden" name="request_id" value="<?=(int)$req['id']?>"><button class="btn secondary">Rejeitar</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
<?php $st=db()->prepare('SELECT pm.user_id,pm.permission,pm.created_at,u.name,u.email FROM property_members pm JOIN users u ON u.id=pm.user_id WHERE pm.property_id=? ORDER BY u.name');$st->execute([$p['id']]);$members=$st->fetchAll();?><h4>Pessoas com acesso</h4><?php if(!$members):?><div class="empty compact">Ainda não há profissionais conectados.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Profissional</th><th>Permissão</th><th>Desde</th><th></th></tr></thead><tbody><?php foreach($members as $m):?><tr><td><?=e($m['name'])?><br><small><?=e($m['email'])?></small></td><td><?=e(['view'=>'Visualizar','edit'=>'Registrar e editar','manage'=>'Gerenciar'][$m['permission']]??$m['permission'])?></td><td><?=date('d/m/Y',strtotime($m['created_at']))?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><input type="hidden" name="member_id" value="<?=(int)$m['user_id']?>"><button class="btn secondary">Revogar acesso</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div><?php endforeach;?>
<?php endif;?>
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
<?php elseif($page==='consultations'):?>
<?php if($consultationError):?><div class="alert"><?=e($consultationError)?></div><?php endif;?>
<div class="consultation-intro"><div><h2>Comunicação entre produtor e profissional</h2><p>Converse diretamente dentro do Germina. As mensagens e os arquivos ficam vinculados à propriedade e só são visíveis para quem tem acesso aprovado.</p></div><span class="consultation-secure">Acesso controlado</span></div>
<?php if(!$props):?><div class="panel empty"><strong>Nenhuma propriedade disponível</strong><p>Cadastre uma propriedade ou solicite acesso a uma propriedade compartilhada para iniciar uma consulta.</p><a class="btn primary" href="index.php?page=team">Equipe e acessos</a></div>
<?php elseif($consultationProperty):?>
<div class="consultation-property-picker"><form method="get"><input type="hidden" name="page" value="consultations"><label>Conversa da propriedade<select name="id" onchange="this.form.submit()"><?php foreach($props as $cp):?><option value="<?=(int)$cp['id']?>" <?=((int)$consultationProperty['id']===(int)$cp['id']?'selected':'')?>><?=e($cp['name'])?></option><?php endforeach;?></select></label></form><span><?=count($consultationMessages)?> mensagens recentes</span></div>
<section class="consultation-chat" data-consultation-chat data-user-id="<?=(int)$user['id']?>" data-property-id="<?=(int)$consultationProperty['id']?>" data-last-id="<?=(int)($consultationMessages?end($consultationMessages)['id']:0)?>">
 <div class="consultation-chat-head"><span class="consultation-avatar">✳</span><div><strong><?=e($consultationProperty['name'])?></strong><small>Produtor e profissionais autorizados</small></div><span class="consultation-online">Conversa da propriedade</span></div>
 <div class="consultation-messages" id="consultationMessages">
 <?php if(!$consultationMessages):?><div class="consultation-empty"><span>✉</span><strong>Comece a conversa</strong><p>Envie uma mensagem, foto, áudio, vídeo ou documento para iniciar o atendimento.</p></div><?php else:foreach($consultationMessages as $cm):$mine=(int)$cm['sender_id']===(int)$user['id'];?><article class="consultation-message <?=$mine?'mine':'theirs'?>" data-message-id="<?=(int)$cm['id']?>"><div class="consultation-message-meta"><strong><?=e($mine?'Você':$cm['sender_name'])?></strong><time><?=date('d/m/Y H:i',strtotime($cm['created_at']))?></time></div><?php if($cm['message_text']):?><p><?=nl2br(e($cm['message_text']))?></p><?php endif;?><?php if($cm['stored_name']):?><div class="consultation-attachment"><?php if(str_starts_with($cm['mime_type'],'image/')):?><a href="consultation_download.php?id=<?=(int)$cm['id']?>" target="_blank"><img src="consultation_download.php?id=<?=(int)$cm['id']?>&inline=1" alt="<?=e($cm['original_name'])?>"></a><?php elseif(str_starts_with($cm['mime_type'],'audio/')):?><audio controls preload="none" src="consultation_download.php?id=<?=(int)$cm['id']?>&inline=1"></audio><?php elseif(str_starts_with($cm['mime_type'],'video/')):?><video controls preload="none" src="consultation_download.php?id=<?=(int)$cm['id']?>&inline=1"></video><?php endif;?><a class="consultation-file-link" href="consultation_download.php?id=<?=(int)$cm['id']?>"><?=e($cm['original_name'])?> · <?=number_format((int)$cm['file_size']/1024,0,',','.')?> KB</a></div><?php endif;?></article><?php endforeach;endif;?>
 </div>
 <form method="post" enctype="multipart/form-data" class="consultation-composer" id="consultationComposer"><?=csrf_field()?><input type="hidden" name="action" value="consultation_send"><input type="hidden" name="property_id" value="<?=(int)$consultationProperty['id']?>"><label class="consultation-text-label" for="consultationMessage">Sua mensagem</label><textarea id="consultationMessage" name="message" rows="3" maxlength="5000" placeholder="Escreva sua mensagem para o produtor ou profissional..."></textarea><div class="consultation-compose-bottom"><label class="consultation-attach"><span>＋ Anexar arquivo</span><input type="file" name="attachment" accept="image/*,audio/*,video/mp4,video/webm,video/quicktime,.pdf,.doc,.docx,.xlsx,.csv,.txt"></label><small>Fotos, documentos, áudios e vídeos · até 20 MB por arquivo*</small><button class="btn primary">Enviar mensagem</button></div></form>
 <p class="consultation-footnote">* O limite efetivo também depende das configurações de upload da hospedagem. Evite enviar arquivos com dados pessoais desnecessários.</p>
</section>
<?php else:?><div class="panel empty"><strong>Selecione uma propriedade para conversar.</strong></div><?php endif;?>
<?php elseif($page==='attachments'):?>
<div class="page-intro"><h2>Documentos e arquivos</h2><p>Envie documentos PDF e imagens vinculados às propriedades (máximo 5 MB).</p></div>
<?php foreach($props as $p):?><div class="panel"><h3><?=e($p['name'])?></h3><?php if(can_edit_property($p)):?><form method="post" enctype="multipart/form-data" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="upload"><input type="hidden" name="property_id" value="<?=(int)$p['id']?>"><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" required><button class="btn primary">Enviar arquivo</button></form><?php endif; $st=db()->prepare('SELECT a.*,u.name uploader FROM attachments a JOIN users u ON u.id=a.uploaded_by WHERE a.property_id=? ORDER BY a.created_at DESC');$st->execute([$p['id']]);$files=$st->fetchAll();?><div class="activity-list"><?php foreach($files as $f):?><div class="activity-item"><span class="activity-dot"></span><div><strong><a href="download.php?id=<?=(int)$f['id']?>"><?=e($f['original_name'])?></a></strong><small><?=e($f['uploader'])?> · <?=number_format($f['file_size']/1024,0)?> KB · <?=date('d/m/Y',strtotime($f['created_at']))?></small></div></div><?php endforeach;?><?php if(!$files):?><div class="empty compact">Nenhum arquivo enviado.</div><?php endif;?></div></div><?php endforeach;?>
<?php elseif($page==='activities'):
$tab=$_GET['tab']??'overview';if(!in_array($tab,['overview','equipment','stock','operations','maintenance','journal'],true))$tab='overview';
$tabLabels=['overview'=>'Resumo','equipment'=>'Veículos e máquinas','stock'=>'Insumos e estoque','operations'=>'Operações','maintenance'=>'Manutenção','journal'=>'Diário de atividades'];
$equipmentKinds=['vehicle'=>'Veículo','implement'=>'Implemento','tool'=>'Ferramenta','machine'=>'Máquina'];
$stockCats=['seed'=>'Sementes','fertilizer'=>'Adubos e fertilizantes','corrective'=>'Corretivos','herbicide'=>'Herbicidas','fungicide'=>'Fungicidas','insecticide'=>'Inseticidas','defensive'=>'Outros defensivos','veterinary'=>'Veterinários','fuel'=>'Combustíveis','lubricant'=>'Lubrificantes','other'=>'Outros'];
?>
<div class="diary-tabs"><?php foreach($tabLabels as $tk=>$tl):?><a class="<?= $tab===$tk?'active':''?>" href="index.php?page=activities&tab=<?=$tk?>"><?=e($tl)?></a><?php endforeach;?></div>
<?php if($tab==='overview'):?>
<div class="diary-summary-grid"><a href="index.php?page=activities&tab=equipment"><span class="diary-summary-icon"><i class="diary-truck"></i></span><strong><?=count($equipment)?></strong><small>Veículos e equipamentos</small><b>Gerenciar frota →</b></a><a href="index.php?page=activities&tab=stock"><span class="diary-summary-icon"><i class="diary-box"></i></span><strong><?=count($stock)?></strong><small>Itens no estoque</small><b>Controlar insumos →</b></a><a href="index.php?page=activities&tab=operations"><span class="diary-summary-icon"><i class="diary-field"></i></span><strong><?=count($equipmentLogs)?></strong><small>Operações registradas</small><b>Ver operações →</b></a><a href="index.php?page=activities&tab=maintenance"><span class="diary-summary-icon"><i class="diary-tools"></i></span><strong><?=count($maintenance)?></strong><small>Manutenções</small><b>Ver histórico →</b></a></div>
<div class="panel diary-callout"><div><span class="eyebrow">GESTÃO OPERACIONAL</span><h3>Registre o que acontece na propriedade</h3><p>Cadastre máquinas, acompanhe insumos, registre operações e mantenha a manutenção em dia.</p></div><a class="btn primary" href="index.php?page=activities&tab=equipment">Começar cadastro</a></div>
<div class="panel"><div class="panel-head"><div><h3>Atividades recentes</h3><p>Últimos registros do diário de campo.</p></div><a class="text-link" href="index.php?page=activities&tab=journal">Ver histórico →</a></div><?php if(!$activities):?><div class="empty">Nenhuma atividade registrada ainda.</div><?php else:?><div class="activity-list"><?php foreach($activities as $a):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($a['title'])?></strong><p><?=e($a['description']??'')?></p><small><?=e($a['property_name'])?> · <?=date('d/m/Y',strtotime($a['activity_date']))?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($tab==='equipment'):?>
<div class="panel form-panel"><div class="panel-head"><div><h3>Cadastrar veículo, máquina ou implemento</h3><p>Cadastre caminhões, tratores, picapes, grades, arados, roçadeiras, ferramentas e outros bens.</p></div></div><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="equipment_create"><label>Propriedade<select name="property_id" required><?php foreach($props as $p):?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select></label><label>Tipo<select name="kind"><?php foreach($equipmentKinds as $k=>$v):?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach;?></select></label><label>Nome / identificação<input name="name" required placeholder="Ex.: Trator Massey Ferguson 4275"></label><label>Categoria<input name="category" placeholder="Ex.: trator, grade aradora, picape"></label><label>Marca<input name="brand"></label><label>Modelo<input name="model"></label><label>Placa ou número patrimonial<input name="identifier"></label><label>Ano<input type="number" name="year_manufactured" min="1950" max="<?=date('Y')+1?>"></label><label>Horímetro (h)<input type="number" step=".1" min="0" name="hour_meter"></label><label>Situação<select name="status"><option value="active">Em operação</option><option value="maintenance">Em manutenção</option><option value="inactive">Inativo</option></select></label><label class="span-2">Observações<textarea name="notes" rows="2"></textarea></label><div class="form-submit"><button class="btn primary">Salvar equipamento</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Frota e equipamentos cadastrados</h3><p><?=count($equipment)?> item(ns) nas propriedades acessíveis.</p></div></div><?php if(!$equipment):?><div class="empty">Nenhum veículo ou equipamento cadastrado.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Equipamento</th><th>Tipo</th><th>Propriedade</th><th>Situação</th><th>Horímetro</th></tr></thead><tbody><?php foreach($equipment as $equi):?><tr><td><strong><?=e($equi['name'])?></strong><br><small><?=e(trim(($equi['brand']??'').' '.($equi['model']??'')))?> <?=e($equi['identifier']??'')?></small></td><td><?=e($equipmentKinds[$equi['kind']]??'Máquina')?></td><td><?=e($equi['property_name'])?></td><td><span class="diary-status <?=e($equi['status'])?>"><?=e(['active'=>'Em operação','maintenance'=>'Em manutenção','inactive'=>'Inativo'][$equi['status']]??$equi['status'])?></span></td><td><?=number_format((float)$equi['hour_meter'],1,',','.')?> h</td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php elseif($tab==='stock'):?>
<div class="panel form-panel"><div class="panel-head"><div><h3>Cadastrar insumo no estoque</h3><p>Controle sementes, adubos, defensivos, corretivos, combustíveis e lubrificantes.</p></div></div><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="stock_create"><label>Propriedade<select name="property_id" required><?php foreach($props as $p):?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select></label><label>Categoria<select name="category"><?php foreach($stockCats as $k=>$v):?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach;?></select></label><label>Nome do produto<input name="name" required placeholder="Ex.: Semente de milho híbrido"></label><label>Marca / fabricante<input name="brand"></label><label>Unidade<select name="unit"><option>kg</option><option>g</option><option>L</option><option>mL</option><option>saca</option><option>unidade</option><option>tonelada</option><option>litro</option></select></label><label>Quantidade inicial<input type="number" min="0" step=".001" name="quantity" value="0"></label><label>Estoque mínimo<input type="number" min="0" step=".001" name="minimum_quantity" value="0"></label><label>Custo por unidade (R$)<input type="number" min="0" step=".01" name="unit_cost"></label><label>Local de armazenamento<input name="storage_location" placeholder="Ex.: Galpão 1"></label><label>Validade<input type="date" name="expiry_date"></label><div class="form-submit"><button class="btn primary">Cadastrar insumo</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Estoque disponível</h3><p>Itens abaixo do mínimo precisam de reposição.</p></div></div><?php if(!$stock):?><div class="empty">Nenhum insumo cadastrado.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Produto</th><th>Categoria</th><th>Propriedade</th><th>Saldo</th><th>Validade</th><th></th></tr></thead><tbody><?php foreach($stock as $it):?><tr><td><strong><?=e($it['name'])?></strong><br><small><?=e($it['brand']??'')?></small></td><td><?=e($stockCats[$it['category']]??'Outros')?></td><td><?=e($it['property_name'])?></td><td><span class="diary-status <?=((float)$it['quantity']<=(float)$it['minimum_quantity']?'low':'active')?>"><?=number_format((float)$it['quantity'],3,',','.')?> <?=e($it['unit'])?></span></td><td><?=e($it['expiry_date']?date('d/m/Y',strtotime($it['expiry_date'])):'—')?></td><td><details class="diary-move"><summary>Movimentar</summary><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="stock_move"><input type="hidden" name="item_id" value="<?=$it['id']?>"><label>Movimento<select name="movement_type"><option value="in">Entrada</option><option value="out">Saída / consumo</option><option value="adjustment">Ajustar saldo</option></select></label><label>Quantidade<input type="number" min=".001" step=".001" name="quantity" required></label><label>Data<input type="date" name="movement_date" value="<?=date('Y-m-d')?>"></label><label>Observação<input name="notes"></label><button class="btn primary">Registrar</button></form></details></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php elseif($tab==='operations'):?>
<div class="panel form-panel"><div class="panel-head"><div><h3>Registrar operação com equipamento</h3><p>Registre a atividade, área trabalhada, horas de uso e combustível consumido.</p></div></div><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="equipment_log"><label>Equipamento<select name="equipment_id" required><?php foreach($equipment as $eq):?><option value="<?=$eq['id']?>"><?=e($eq['name'])?> · <?=e($eq['property_name'])?></option><?php endforeach;?></select></label><label>Data<input type="date" name="operation_date" value="<?=date('Y-m-d')?>"></label><label>Operação<input name="operation" placeholder="Ex.: Gradagem, plantio, transporte"></label><label>Talhão (opcional)<select name="field_id"><option value="0">Área geral</option><?php foreach($fields as $f):?><option value="<?=$f['id']?>"><?=e($f['name'])?></option><?php endforeach;?></select></label><label>Área trabalhada (ha)<input type="number" min="0" step=".01" name="area_ha"></label><label>Horas trabalhadas<input type="number" min="0" step=".1" name="hours_used"></label><label>Combustível (L)<input type="number" min="0" step=".1" name="fuel_liters"></label><label class="span-2">Descrição<textarea name="description" rows="2" required></textarea></label><div class="form-submit"><button class="btn primary">Registrar operação</button></div></form></div>
<div class="panel"><div class="panel-head"><div><h3>Histórico de operações</h3><p>Utilização da frota nas propriedades.</p></div></div><?php if(!$equipmentLogs):?><div class="empty">Nenhuma operação registrada.</div><?php else:?><div class="activity-list"><?php foreach($equipmentLogs as $log):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($log['operation'])?> · <?=e($log['equipment_name'])?></strong><p><?=e($log['description'])?></p><small><?=e($log['property_name'])?> · <?=date('d/m/Y',strtotime($log['operation_date']))?> · <?=number_format((float)$log['hours_used'],1,',','.')?> h · <?=number_format((float)$log['fuel_liters'],1,',','.')?> L</small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($tab==='maintenance'):?>
<div class="panel form-panel"><div class="panel-head"><div><h3>Registrar manutenção</h3><p>Controle revisões preventivas, consertos, peças e custos da frota.</p></div></div><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="maintenance_create"><label>Equipamento<select name="equipment_id" required><?php foreach($equipment as $eq):?><option value="<?=$eq['id']?>"><?=e($eq['name'])?> · <?=e($eq['property_name'])?></option><?php endforeach;?></select></label><label>Tipo<select name="maintenance_type"><option value="preventive">Preventiva</option><option value="corrective">Corretiva</option></select></label><label>Data<input type="date" name="maintenance_date" value="<?=date('Y-m-d')?>"></label><label>Custo (R$)<input type="number" min="0" step=".01" name="cost"></label><label>Próxima revisão<input type="date" name="next_due_date"></label><label>Oficina / prestador<input name="provider"></label><label class="span-2">Serviço realizado<textarea name="description" required rows="2"></textarea></label><div class="form-submit"><button class="btn primary">Salvar manutenção</button></div></form></div>
<div class="panel"><?php if(!$maintenance):?><div class="empty">Nenhuma manutenção registrada.</div><?php else:?><div class="activity-list"><?php foreach($maintenance as $m):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($m['equipment_name'])?> · <?=e($m['maintenance_type']==='preventive'?'Preventiva':'Corretiva')?></strong><p><?=e($m['description'])?></p><small><?=e($m['property_name'])?> · <?=date('d/m/Y',strtotime($m['maintenance_date']))?> · R$ <?=number_format((float)$m['cost'],2,',','.')?><?= $m['next_due_date']?' · Próxima revisão '.date('d/m/Y',strtotime($m['next_due_date'])):''?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php elseif($tab==='journal'):?>
<div class="panel"><?php if(!$activities):?><div class="empty">Nenhuma atividade registrada.</div><?php else:?><div class="activity-list"><?php foreach($activities as $a):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($a['title'])?></strong><p><?=e($a['description']??'')?></p><small><?=e($a['property_name'])?> · <?=date('d/m/Y',strtotime($a['activity_date']))?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php endif;?><?php if(!$activities):?><div class="empty">Nenhuma atividade registrada.</div><?php else:?><div class="activity-list"><?php foreach($activities as $a):?><div class="activity-item"><span class="activity-dot"></span><div><strong><?=e($a['title'])?></strong><p><?=e($a['description']??'')?></p><small><?=e($a['property_name'])?> · <?=date('d/m/Y',strtotime($a['activity_date']))?></small></div></div><?php endforeach;?></div><?php endif;?></div>
<?php else:?><div class="panel empty">Módulo não encontrado.</div><?php endif;?>
<footer class="footer">Germina · Gestão no campo · <?=date('Y')?></footer></div></main></div><script>
document.querySelectorAll('[data-mobile-back]').forEach(button=>button.addEventListener('click',()=>{
  try {
    const previous=new URL(document.referrer);
    if(previous.origin===window.location.origin && window.history.length>1){window.history.back();return;}
  } catch(e) {}
  window.location.href='index.php';
}));
document.querySelectorAll('[data-copy-code]').forEach(button=>button.addEventListener('click',async()=>{
  const code=document.getElementById('newAccessCode')?.textContent?.trim();
  if(!code)return;
  try{await navigator.clipboard.writeText(code);button.textContent='Copiado!';}
  catch(e){button.textContent='Selecione e copie o código';}
}));
<script>
(function(){
 const chat=document.querySelector('[data-consultation-chat]');
 if(!chat)return;
 const list=document.getElementById('consultationMessages');
 let last=Number(chat.dataset.lastId||0);
 function addMessage(m){
  const empty=list.querySelector('.consultation-empty');if(empty)empty.remove();
  const mine=Number(m.sender_id)===Number(chat.dataset.userId||0);
  const article=document.createElement('article');article.className='consultation-message '+(mine?'mine':'theirs');article.dataset.messageId=m.id;
  const meta=document.createElement('div');meta.className='consultation-message-meta';
  const name=document.createElement('strong');name.textContent=mine?'Você':m.sender_name;
  const time=document.createElement('time');time.textContent=new Date(String(m.created_at).replace(' ','T')).toLocaleString('pt-BR');
  meta.append(name,time);article.append(meta);
  if(m.message_text){const p=document.createElement('p');p.textContent=m.message_text;article.append(p);}
  if(m.original_name){
   const wrap=document.createElement('div');wrap.className='consultation-attachment';
   const url='consultation_download.php?id='+encodeURIComponent(m.id);
   if((m.mime_type||'').startsWith('image/')){const a=document.createElement('a');a.href=url;a.target='_blank';const img=document.createElement('img');img.src=url+'&inline=1';img.alt=m.original_name;a.append(img);wrap.append(a);}
   else if((m.mime_type||'').startsWith('audio/')){const audio=document.createElement('audio');audio.controls=true;audio.preload='none';audio.src=url+'&inline=1';wrap.append(audio);}
   else if((m.mime_type||'').startsWith('video/')){const video=document.createElement('video');video.controls=true;video.preload='none';video.src=url+'&inline=1';wrap.append(video);}
   const a=document.createElement('a');a.className='consultation-file-link';a.href=url;a.textContent=m.original_name+' · '+Math.round(Number(m.file_size)/1024)+' KB';wrap.append(a);article.append(wrap);
  }
  list.append(article);last=Math.max(last,Number(m.id));
 }
 async function poll(){
  try{const r=await fetch('consultation_feed.php?property_id='+encodeURIComponent(chat.dataset.propertyId)+'&after='+last,{credentials:'same-origin',cache:'no-store'});if(!r.ok)return;const data=await r.json();(data.messages||[]).forEach(addMessage);if(data.messages&&data.messages.length)list.scrollTop=list.scrollHeight;}
  catch(e){}
 }
 list.scrollTop=list.scrollHeight;
 window.setInterval(poll,10000);
 const form=document.getElementById('consultationComposer');
 if(form)form.addEventListener('submit',()=>{const b=form.querySelector('button[type="submit"],button:not([type])');if(b){b.disabled=true;b.textContent='Enviando...';}});
})();
</script>
</body></html>