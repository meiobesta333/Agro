<?php
declare(strict_types=1);
// Execute uma única vez após publicar. Exclua este arquivo do servidor após o sucesso.
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(503);exit('Configure config.php antes da atualização.');}
$c=require $configFile;
try{
 $pdo=new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$c['db_host'],$c['db_name']),$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  message_text TEXT NULL,
  original_name VARCHAR(255) NULL,
  stored_name VARCHAR(100) NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_consultation_property_message(property_id,id),
  INDEX idx_consultation_sender(sender_id,created_at),
  CONSTRAINT fk_consultation_property FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_consultation_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 header('Content-Type:text/html;charset=utf-8');
 echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font:16px system-ui;max-width:700px;margin:60px auto;padding:20px;color:#24372b"><h1 style="color:#216b49">Consultas atualizado</h1><p>A tabela de mensagens foi criada sem apagar dados existentes.</p><strong>Exclua upgrade_consultas.php do servidor agora.</strong></body></html>';
}catch(Throwable $e){http_response_code(500);error_log('Germina consultas migration: '.$e->getMessage());echo 'Não foi possível atualizar o banco. Consulte o log PHP e não repita antes de verificar o erro.';}
