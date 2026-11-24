<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$u=current_user();
if(!$u){http_response_code(403);echo json_encode(['error'=>'Acesso negado.']);exit;}
$pid=(int)($_GET['property_id']??0);$after=max(0,(int)($_GET['after']??0));
$p=property_access($pid);
if(!$p){http_response_code(403);echo json_encode(['error'=>'Sem acesso à propriedade.']);exit;}
try{
 $st=db()->prepare('SELECT m.id,m.sender_id,m.message_text,m.original_name,m.mime_type,m.file_size,m.created_at,u.name sender_name FROM consultation_messages m JOIN users u ON u.id=m.sender_id WHERE m.property_id=? AND m.id>? ORDER BY m.id ASC LIMIT 50');
 $st->execute([$pid,$after]);$rows=$st->fetchAll();
 echo json_encode(['messages'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);error_log('Germina consultation feed: '.$e->getMessage());echo json_encode(['error'=>'Não foi possível atualizar as mensagens.']);}
