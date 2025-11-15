<?php
// =======================================
//  Configuración de base de datos (PDO)
// =======================================

// Archivo de configuración local (no se sube a git)
$localConfigPath = __DIR__ . '/config.local.php';

// Si existe config.local.php, lo usamos
if (file_exists($localConfigPath)) {
    $cfg = require $localConfigPath;
} else {
    // Valores por defecto (útiles si algún día hay otro entorno)
    $cfg = [
        'db_host'    => '127.0.0.1',
        'db_name'    => 'sansouci_desk',
        'db_user'    => 'root',
        'db_pass'    => '',
        'db_charset' => 'utf8mb4',
    ];
}

$host    = $cfg['db_host'];
$db      = $cfg['db_name'];
$user    = $cfg['db_user'];
$pass    = $cfg['db_pass'];
$charset = $cfg['db_charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// =======================================
//  Configuración de correo
// =======================================

$correo_soporte = 'soporte@sansouci.com.do';
$nombre_empresa = 'Sansouci Puerto de Santo Domingo';
?>
