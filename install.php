<?php
declare(strict_types=1);
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Primeiro copie config.example.php para config.php e preencha os dados do MySQL.');
}
$config = require $configFile;
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']),
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_meta (
        meta_key VARCHAR(80) PRIMARY KEY,
        meta_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $installed = $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key='installed'")->fetchColumn();
    if ($installed) {
        http_response_code(409);
        exit('O Agro já foi instalado. Exclua install.php do servidor e acesse index.php.');
    }

    $queries = [
      "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('producer','professional') NOT NULL DEFAULT 'producer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      "CREATE TABLE IF NOT EXISTS properties (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_id INT UNSIGNED NOT NULL,
        name VARCHAR(160) NOT NULL,
        city VARCHAR(120) NULL,
        state CHAR(2) NULL,
        area_ha DECIMAL(12,2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_properties_owner FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_properties_owner(owner_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      "CREATE TABLE IF NOT EXISTS fields (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        property_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        area_ha DECIMAL(12,2) NULL,
        crop_name VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_fields_property FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
        INDEX idx_fields_property(property_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      "CREATE TABLE IF NOT EXISTS activities (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        property_id INT UNSIGNED NOT NULL,
        field_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT NULL,
        activity_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_activities_property FOREIGN KEY(property_id) REFERENCES properties(id) ON DELETE CASCADE,
        CONSTRAINT fk_activities_field FOREIGN KEY(field_id) REFERENCES fields(id) ON DELETE SET NULL,
        CONSTRAINT fk_activities_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_activities_property_date(property_id,activity_date)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
    $stmt = $pdo->prepare("INSERT INTO app_meta(meta_key,meta_value) VALUES('installed',?)");
    $stmt->execute([date('c')]);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Agro instalado</title><body style="font:16px system-ui;max-width:680px;margin:60px auto;padding:20px;color:#202923"><h1 style="color:#1f6b45">Instalação concluída!</h1><p>As tabelas do Agro foram criadas com sucesso.</p><p><strong>Próximo passo:</strong> exclua imediatamente o arquivo <code>install.php</code> do servidor.</p><p><a href="index.php?page=register">Criar primeira conta</a></p></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Agro installer: ' . $e->getMessage());
    echo '<h1>Não foi possível concluir a instalação</h1><p>Confira os dados do MySQL no config.php e confirme que o banco foi criado no painel da hospedagem.</p><p>Se o problema continuar, consulte o log de erros do PHP no painel.</p>';
}
