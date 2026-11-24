<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if(!current_user()){http_response_code(403);exit('Acesso negado.');}
$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT a.* FROM attachments a JOIN properties p ON p.id=a.property_id LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE a.id=? AND (p.owner_id=? OR pm.user_id=?) LIMIT 1");
$u=current_user();$st->execute([$u['id'],$id,$u['id'],$u['id']]);$file=$st->fetch();
if(!$file){http_response_code(404);exit('Arquivo não encontrado.');}
$path=__DIR__.'/storage/'.$file['stored_name'];
if(!is_file($path)){http_response_code(404);exit('Arquivo indisponível.');}
header('Content-Type: '.$file['mime_type']);
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.rawurlencode($file['original_name']).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
