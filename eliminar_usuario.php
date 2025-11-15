<?php
session_start();
require 'config.php';
if($_SESSION['user']['rol']!='superadmin') die("Acceso denegado");
$id = $_GET['id'];
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
header('Location: mantenimiento.php');
?>