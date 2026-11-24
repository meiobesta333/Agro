<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
$checks = [];
$checks[] = ['PHP 8.1+', PHP_VERSION_ID >= 80100, PHP_VERSION];
$checks[] = ['PDO disponível', extension_loaded('PDO'), extension_loaded('PDO') ? 'Ativo' : 'Ausente'];
$checks[] = ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'Ativo' : 'Ausente'];

$databaseOk = false;
$installed = false;
$tableCount = 0;
try {
    $pdo = db();
    $databaseOk = (bool)$pdo->query('SELECT 1')->fetchColumn();
    $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    try {
        $installed = (bool)$pdo->query("SELECT meta_value FROM app_meta WHERE meta_key='installed'")->fetchColumn();
    } catch (Throwable) {
        $installed = false;
    }
} catch (Throwable) {
    $databaseOk = false;
}
$checks[] = ['Conexão MySQL', $databaseOk, $databaseOk ? 'Conectado' : 'Falhou — confira config.php e o host MySQL'];
$checks[] = ['Tabelas do banco', $tableCount >= 4, $tableCount . ' tabela(s) encontrada(s)'];
$checks[] = ['Instalação do Agro', $installed, $installed ? 'Instalado' : 'Ainda não instalado'];

$allOk = true;
foreach ($checks as $check) if (!$check[1]) $allOk = false;
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico · Agro</title><style>body{font:15px system-ui;background:#f6f8f6;color:#202923;margin:0;padding:30px}.wrap{max-width:680px;margin:30px auto;background:#fff;padding:28px;border:1px solid #e7ece8;border-radius:16px}h1{margin-top:0}.item{padding:13px 0;border-bottom:1px solid #edf0ed;display:flex;justify-content:space-between;gap:15px}.ok{color:#1f6b45}.bad{color:#a23e2d}.hint{color:#6b756e;font-size:13px;line-height:1.6;margin-top:20px}</style></head><body><main class="wrap"><h1>Diagnóstico do Agro</h1><p><?= $allOk ? 'Ambiente básico pronto.' : 'Há verificações pendentes.' ?></p>
<?php foreach ($checks as [$label,$ok,$detail]): ?><div class="item"><strong><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></strong><span class="<?= $ok?'ok':'bad' ?>"><?= htmlspecialchars($detail,ENT_QUOTES,'UTF-8') ?></span></div><?php endforeach; ?>
<p class="hint">Por segurança, exclua este arquivo (diagnostico.php) do servidor depois de concluir os testes. Se o banco ainda não estiver instalado, execute install.php uma vez e rode o diagnóstico novamente.</p><p><a href="index.php">Abrir o Agro</a></p></main></body></html>
