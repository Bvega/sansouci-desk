<?php
require 'config.php';
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['rol'] != 'superadmin'){
    die("Acceso denegado");
}

$id = $_GET['id'] ?? 0;
if($id){
    $pdo->prepare("DELETE FROM respuestas WHERE ticket_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tickets WHERE id = ?")->execute([$id]);
}
header('Location: tickets.php');
exit();
?>