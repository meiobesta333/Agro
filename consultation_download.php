<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){http_response_code(403);exit('Acesso negado.');}
$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT m.* FROM consultation_messages m JOIN properties p ON p.id=m.property_id LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE m.id=? AND (p.owner_id=? OR pm.user_id=?) LIMIT 1');
$st->execute([$u['id'],$id,$u['id'],$u['id']]);$file=$st->fetch();
if(!$file||empty($file['stored_name'])){http_response_code(404);exit('Arquivo não encontrado.');}
$path=__DIR__.'/storage/'.$file['stored_name'];if(!is_file($path)){http_response_code(404);exit('Arquivo indisponível.');}
$inline=isset($_GET['inline'])&&$_GET['inline']==='1'&&(str_starts_with($file['mime_type'],'image/')||str_starts_with($file['mime_type'],'audio/')||str_starts_with($file['mime_type'],'video/'));
header('Content-Type: '.$file['mime_type']);header('Content-Length: '.filesize($path));
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.rawurlencode($file['original_name']).'"');
header('X-Content-Type-Options: nosniff');header('Cache-Control: private, max-age=300');readfile($path);
