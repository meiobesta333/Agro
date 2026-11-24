<?php
declare(strict_types=1);
// Execute uma única vez após publicar. Exclua este arquivo do servidor após o sucesso.
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(503);exit('Configure config.php antes da atualização.');}
$c=require $configFile;
try{
 $pdo=new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$c['db_host'],$c['db_name']),$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sql=[
  "CREATE TABLE IF NOT EXISTS property_access_codes (
   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   property_id INT UNSIGNED NOT NULL,
   code_hash CHAR(64) NOT NULL UNIQUE,
   status ENUM('active','pending','used','revoked') NOT NULL DEFAULT 'active',
   created_by INT UNSIGNED NOT NULL,
   expires_at DATETIME NOT NULL,
   used_at DATETIME NULL,
   revoked_at DATETIME NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   INDEX idx_access_code_property_status(property_id,status),
   CONSTRAINT fk_pac_property FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
   CONSTRAINT fk_pac_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  "CREATE TABLE IF NOT EXISTS property_access_requests (
   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   property_id INT UNSIGNED NOT NULL,
   access_code_id INT UNSIGNED NOT NULL,
   requested_by INT UNSIGNED NOT NULL,
   status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
   decided_by INT UNSIGNED NULL,
   decision_note VARCHAR(500) NULL,
   requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   decided_at DATETIME NULL,
   INDEX idx_access_requests_property_status(property_id,status,requested_at),
   INDEX idx_access_requests_user_status(requested_by,status),
   CONSTRAINT fk_par_property FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
   CONSTRAINT fk_par_code FOREIGN KEY(access_code_id) REFERENCES property_access_codes(id) ON DELETE CASCADE,
   CONSTRAINT fk_par_requester FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE CASCADE,
   CONSTRAINT fk_par_decider FOREIGN KEY(decided_by) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
 ];
 foreach($sql as $q)$pdo->exec($q);
 header('Content-Type:text/html;charset=utf-8');
 echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font:16px system-ui;max-width:700px;margin:60px auto;padding:20px;color:#24372b"><h1 style="color:#216b49">Compartilhamento atualizado</h1><p>As tabelas de códigos de acesso e solicitações foram criadas sem apagar os membros ou dados existentes.</p><strong>Exclua upgrade_compartilhamento.php do servidor agora.</strong></body></html>';
}catch(Throwable $e){http_response_code(500);error_log('Germina compartilhamento migration: '.$e->getMessage());echo 'Não foi possível atualizar o banco. Consulte o log PHP e não repita antes de verificar o erro.';}
