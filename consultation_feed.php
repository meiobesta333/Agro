<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$u=current_user();
if(!$u){http_response_code(403);echo json_encode(['error'=>'Acesso negado.']);exit;}
$conversationId=(int)($_GET['conversation_id']??0);$after=max(0,(int)($_GET['after']??0));
$st=db()->prepare('SELECT c.id FROM consultation_conversations c JOIN properties p ON p.id=c.property_id LEFT JOIN property_members pm ON pm.property_id=c.property_id AND pm.user_id=? WHERE c.id=? AND ((c.producer_id=? AND p.owner_id=?) OR (c.professional_id=? AND pm.user_id=?)) LIMIT 1');
$st->execute([$u['id'],$conversationId,$u['id'],$u['id'],$u['id'],$u['id']]);
if(!$st->fetch()){http_response_code(403);echo json_encode(['error'=>'Sem acesso à conversa.']);exit;}
try{
 $st=db()->prepare('SELECT m.id,m.sender_id,m.message_text,m.original_name,m.mime_type,m.file_size,m.created_at,u.name sender_name FROM consultation_messages m JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=? AND m.id>? ORDER BY m.id ASC LIMIT 50');
 $st->execute([$conversationId,$after]);$rows=$st->fetchAll();
 echo json_encode(['messages'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);error_log('Germina consultation feed: '.$e->getMessage());echo json_encode(['error'=>'Não foi possível atualizar as mensagens.']);}
