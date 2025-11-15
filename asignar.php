<?php
session_start();
require 'config.php';
if ($_POST['ticket_id']) {
    $stmt = $pdo->prepare("UPDATE tickets SET agente_id = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id'], $_POST['ticket_id']]);
}
header('Location: panel.php');
?>