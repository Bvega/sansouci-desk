<?php
require __DIR__ . '/config.php';
require __DIR__ . '/config_email.php';

$cliente_email = trim($_GET['email'] ?? '');
$ticket_id     = (int)($_GET['ticket_id'] ?? 0);
$mensaje       = '';
$error         = '';

// Si hay email, buscamos tickets
$tickets = [];
if ($cliente_email !== '') {
    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS agente_nombre 
        FROM tickets t 
        LEFT JOIN users u ON t.agente_id = u.id 
        WHERE t.cliente_email = ? 
        ORDER BY t.creado_el DESC
    ");
    $stmt->execute([$cliente_email]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Procesar respuesta del cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta      = trim($_POST['respuesta'] ?? '');
    $ticket_id_post = (int)($_POST['ticket_id'] ?? 0);

    if (!$ticket_id_post) {
        $error = "Ticket no válido.";
    } elseif ($respuesta === '') {
        $error = "Escribe tu respuesta.";
    } else {
        try {
            // Guardar respuesta del cliente en la tabla respuestas
            $stmt = $pdo->prepare("
                INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                VALUES (?, ?, 'cliente', ?, NOW())
            ");
            $stmt->execute([$ticket_id_post, $respuesta, $cliente_email]);

            // Buscar número de ticket para el asunto del correo
            $tStmt = $pdo->prepare("SELECT id, numero FROM tickets WHERE id = ?");
            $tStmt->execute([$ticket_id_post]);
            $tRow   = $tStmt->fetch(PDO::FETCH_ASSOC);
            $numero = $tRow['numero'] ?? ('TCK-' . str_pad($ticket_id_post, 5, '0', STR_PAD_LEFT));

            // ==============================
            //  NOTIFICACIÓN INTERNA POR CORREO
            // ==============================
            $cfg        = loadEmailConfig($pdo);
            $internalTo = $cfg['from_email'] ?: ($cfg['smtp_user'] ?? '');

            if ($internalTo) {
                $subject = "Nueva respuesta de cliente en ticket #{$numero}";

                $bodyHtml = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <h1 style='color: #003087; text-align: center;'>NUEVA RESPUESTA DE CLIENTE</h1>
                        <p><strong>Cliente:</strong> " . htmlspecialchars($cliente_email, ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Ticket:</strong> #{$numero}</p>
                        <hr>
                        <p style='background: #f0f8ff; padding: 20px; border-radius: 10px;'>
                            " . nl2br(htmlspecialchars($respuesta, ENT_QUOTES, 'UTF-8')) . "
                        </p>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/responder.php?ticket_id={$ticket_id_post}' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                               RESPONDER EN EL PANEL
                            </a>
                        </div>
                    </div>
                ";

                // Usamos el helper centralizado de correo
                @sendSupportMail(
                    $internalTo,
                    $subject,
                    $bodyHtml,
                    $cliente_email // reply-to al cliente
                );
            }

            // Redirigir para evitar reenvíos de formulario
            header("Location: portal_cliente_tickets.php?email=" . urlencode($cliente_email) . "&ticket_id=" . $ticket_id_post);
            exit;
        } catch (Exception $e) {
            $error = "Error al enviar la respuesta.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sansouci Desk - Mis Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #001f3f 0%, #003087 100%); font-family: 'Segoe UI', sans-serif; }
        .card { backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.97); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="card rounded-2xl shadow-2xl w-full max-w-6xl border border-blue-900">
        <!-- Header -->
        <div class="text-center pt-10 pb-8 bg-gradient-to-b from-blue-900 to-blue-800 rounded-t-2xl">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-20 mx-auto">
            <h1 class="text-4xl font-bold text-white mt-6">PORTAL DE CLIENTES</h1>
            <p class="text-2xl text-blue-200 mt-2">Mis Tickets</p>
        </div>

        <div class="p-12">
            <?php if ($error): ?>
                <div class="bg-red-100 border-4 border-red-600 text-red-800 px-8 py-6 rounded-2xl mb-10 text-2xl font-bold text-center">
                    <i class="fas fa-exclamation-triangle mr-4"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($tickets) && $cliente_email !== '' && !$error): ?>
                <div class="bg-yellow-100 border-4 border-yellow-600 text-yellow-800 px-8 py-6 rounded-2xl mb-10 text-2xl font-bold text-center">
                    <i class="fas fa-info-circle mr-4"></i>No se encontraron tickets para este correo.
                </div>
            <?php endif; ?>

            <!-- FORMULARIO PARA BUSCAR POR EMAIL -->
            <form method="GET" class="mb-12">
                <div class="flex gap-6 max-w-2xl mx-auto">
                    <input type="email" name="email" value="<?= htmlspecialchars($cliente_email) ?>" required
                           class="flex-1 p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition shadow-lg"
                           placeholder="tuemail@cliente.com">
                    <button type="submit"
                            class="bg-blue-900 text-white px-12 py-6 rounded-2xl font-bold text-2xl hover:bg-blue-800 shadow-lg">
                        <i class="fas fa-search mr-4"></i> BUSCAR
                    </button>
                </div>
            </form>

            <!-- LISTA DE TICKETS -->
            <?php if (!empty($tickets)): ?>
                <div class="space-y-12">
                    <?php foreach ($tickets as $t):
                        $numero  = $t['numero'] ?? 'TCK-' . str_pad($t['id'], 5, '0', STR_PAD_LEFT);
                        $abierto = ($t['id'] === (int)($_GET['ticket_id'] ?? 0));
                    ?>
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-xl p-8 border-l-8 border-blue-900 <?= $abierto ? 'ring-8 ring-blue-300' : '' ?>">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <span class="text-3xl font-bold text-blue-900">#<?= htmlspecialchars($numero) ?></span>
                                <span class="ml-6 px-6 py-3 rounded-full text-xl font-bold <?= $t['estado']=='abierto'?'bg-green-200 text-green-800':($t['estado']=='cerrado'?'bg-gray-400 text-white':'bg-yellow-200 text-yellow-800') ?>">
                                    <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                                </span>
                                <span class="ml-4 px-6 py-3 rounded-full text-xl font-bold <?= $t['prioridad']=='urgente'?'bg-red-200 text-red-800':($t['prioridad']=='alta'?'bg-orange-200 text-orange-800':'bg-blue-200 text-blue-800') ?>">
                                    <?= ucfirst($t['prioridad']) ?>
                                </span>
                            </div>
                            <small class="text-gray-600 text-xl"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></small>
                        </div>

                        <h3 class="text-2xl font-bold text-blue-900 mb-4"><?= htmlspecialchars($t['asunto']) ?></h3>
                        <p class="text-lg text-gray-700 mb-6"><?= nl2br(htmlspecialchars($t['mensaje'])) ?></p>

                        <div class="flex flex-wrap gap-6 text-lg mb-8">
                            <span><strong>Tipo de Servicio:</strong> <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?></span>
                            <span><strong>Agente:</strong> <?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?></span>
                        </div>

                        <!-- BOTÓN PARA ABRIR RESPUESTA -->
                        <div class="text-right mb-8">
                            <a href="portal_cliente_tickets.php?email=<?= urlencode($cliente_email) ?>&ticket_id=<?= $t['id'] ?>"
                               class="bg-blue-600 text-white px-12 py-6 rounded-2xl font-bold text-2xl hover:bg-blue-700 shadow-lg inline-block">
                                <i class="fas fa-reply mr-4"></i> RESPONDER
                            </a>
                        </div>

                        <!-- HISTORIAL DE RESPUESTAS -->
                        <?php
                        $stmt_resp = $pdo->prepare("SELECT * FROM respuestas WHERE ticket_id = ? ORDER BY creado_el");
                        $stmt_resp->execute([$t['id']]);
                        $respuestas = $stmt_resp->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (!empty($respuestas)):
                        ?>
                        <div class="border-t-4 border-blue-300 pt-8">
                            <h4 class="text-2xl font-bold text-blue-900 mb-6">Historial de Conversación</h4>
                            <div class="space-y-6">
                                <?php foreach ($respuestas as $r): ?>
                                <div class="bg-white rounded-xl shadow-lg p-6 border-l-8 <?= $r['autor']=='cliente' ? 'border-green-600' : 'border-blue-600' ?>">
                                    <div class="flex justify-between items-start mb-4">
                                        <span class="font-bold text-xl <?= $r['autor']=='cliente'?'text-green-800':'text-blue-800' ?>">
                                            <?= $r['autor']=='cliente'?'Tú (Cliente)':'Agente' ?>
                                        </span>
                                        <small class="text-gray-600"><?= date('d/m/Y H:i', strtotime($r['creado_el'])) ?></small>
                                    </div>
                                    <p class="text-lg text-gray-700"><?= nl2br(htmlspecialchars($r['mensaje'])) ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- FORMULARIO DE RESPUESTA (solo si está abierto el ticket) -->
                        <?php if ($abierto && $t['estado'] !== 'cerrado'): ?>
                        <div class="mt-12 border-t-4 border-green-300 pt-8">
                            <h4 class="text-2xl font-bold text-green-800 mb-6">Enviar Respuesta</h4>
                            <form method="POST">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <textarea name="respuesta" rows="6" required
                                          class="w-full p-8 border-4 border-green-300 rounded-3xl text-2xl focus:border-green-600 transition resize-none shadow-xl"
                                          placeholder="Escribe tu respuesta aquí..."></textarea>
                                <div class="text-right mt-8">
                                    <button type="submit"
                                            class="bg-green-600 text-white px-20 py-10 rounded-full text-3xl font-bold hover:bg-green-700 shadow-2xl transform hover:scale-105 transition">
                                        <i class="fas fa-paper-plane mr-8"></i> ENVIAR RESPUESTA
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-20 text-center">
                <a href="portal_cliente.php" class="bg-green-600 text-white px-24 py-12 rounded-full text-4xl font-bold hover:bg-green-700 shadow-2xl inline-block">
                    <i class="fas fa-plus mr-8"></i> CREAR NUEVO TICKET
                </a>
            </div>

            <!-- FOOTER UNIFICADO -->
            <p class="mt-10 text-xs text-center text-slate-400">
                © <?= date('Y') ?> Sansouci Puerto de Santo Domingo
            </p>
        </div>
    </div>
</body>
</html>
