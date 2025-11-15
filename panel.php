<?php
session_start();
require 'config.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
$user = $_SESSION['user'];

$stmt = $pdo->query("SELECT t.*, u.nombre as agente FROM tickets t LEFT JOIN users u ON t.agente_id = u.id ORDER BY t.creado_el DESC");
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Agentes - Sansouci Desk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="bg-blue-900 text-white p-6 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold">Sansouci Desk ⚓</h1>
            <p>Bienvenido, <strong><?= htmlspecialchars($user['nombre']) ?></strong> (<?= $user['rol'] ?>)</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto p-8">
        <h2 class="text-2xl font-bold mb-6 text-blue-900">Tickets Recibidos (<?= count($tickets) ?>)</h2>
        
        <?php if (empty($tickets)): ?>
            <p class="text-center text-gray-600 text-xl">¡No hay tickets aún! Prueba crear uno desde el portal de clientes.</p>
        <?php else: ?>
            <div class="grid gap-6">
                <?php foreach ($tickets as $t): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-8 <?= $t['prioridad']=='urgente' ? 'border-red-600' : 'border-blue-600' ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-2xl font-bold">#<?= $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT) ?></span>
                                <span class="ml-4 px-3 py-1 rounded-full text-sm <?= $t['estado']=='abierto' ? 'bg-green-200 text-green-800' : 'bg-gray-200' ?>">
                                    <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                                </span>
                            </div>
                            <small class="text-gray-500"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></small>
                        </div>
                        <h3 class="text-xl font-semibold mt-3"><?= htmlspecialchars($t['asunto']) ?></h3>
                        <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($t['mensaje'])) ?></p>
                        <div class="mt-4 flex justify-between items-center">
                            <p><strong>Cliente:</strong> <?= htmlspecialchars($t['cliente_email']) ?></p>
                            <?php if ($t['agente']): ?>
                                <p class="text-blue-700"><strong>Asignado a:</strong> <?= $t['agente'] ?></p>
                            <?php else: ?>
                                <form method="POST" action="asignar.php" class="inline">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Asignarme</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>