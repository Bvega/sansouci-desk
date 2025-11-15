<?php
$host = 'localhost';
$db   = 'sansouci_desk';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// Correo
$correo_soporte = 'soporte@sansouci.com.do';
$nombre_empresa = 'Sansouci Puerto de Santo Domingo';
?>