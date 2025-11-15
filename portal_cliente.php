<?php
require 'config.php';

$cliente_email = trim($_GET['email'] ?? '');
$ticket_id = $_GET['ticket_id'] ?? 0;
$mensaje = '';
$error = '';

if(empty($cliente_email)){
    $error = "Ingresa tu correo para ver tus tickets";
} else {
    // BUSCAR TICKETS DEL CLIENTE
    $stmt = $pdo->prepare("SELECT t.*, u.nombre as agente_nombre 
                           FROM tickets t 
                           LEFT JOIN users u ON t.agente_id = u.id 
                           WHERE t.cliente_email = ? 
                           ORDER BY t.creado_el DESC");
    $stmt->execute([$cliente_email]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// PROCESAR RESPUESTA DEL CLIENTE
if($_POST && $ticket_id){
    $respuesta = trim($_POST['respuesta'] ?? '');
    if(empty($respuesta)){
        $error = "Escribe tu respuesta";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email) 
                                   VALUES (?, ?, 'cliente', ?)");
            $stmt->execute([$ticket_id, $respuesta, $cliente_email]);

            // NOTIFICAR A AGENTES (PHPMailer)
            require 'phpmailer/src/Exception.php';
            require 'phpmailer/src/PHPMailer.php';
            require 'phpmailer/src/SMTP.php';

            $stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
            $config_email = $stmt->fetch();

            if($config_email && !empty($config_email['smtp_usuario'])){
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_usuario'];
                    $mail->Password   = $config_email['smtp_clave'];
                    $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?? '';
                    $mail->Port       = $config_email['smtp_port'];

                    $mail->setFrom($config_email['smtp_usuario'], $config_email['smtp_from_name']);
                    $mail->addAddress($config_email['smtp_from_email'] ?: $config_email['smtp_usuario']);

                    $mail->isHTML(true);
                    $mail->Subject = "Nueva respuesta de cliente";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <h1 style='color: #003087; text-align: center;'>NUEVA RESPUESTA DE CLIENTE</h1>
                        <p><strong>Cliente:</strong> $cliente_email</p>
                        <p><strong>Ticket ID:</strong> $ticket_id</p>
                        <hr>
                        <p style='background: #f0f8ff; padding: 20px; border-radius: 10px;'>
                            " . nl2br(htmlspecialchars($respuesta)) . "
                        </p>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/responder.php?ticket_id=$ticket_id' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                               RESPONDER EN EL PANEL
                            </a>
                        </div>
                    </div>";

                    $mail->send();
                } catch (Exception $e) {
                    // Silencioso
                }
            }

            $mensaje = "¡Respuesta enviada con éxito!";
            header("Location: portal_cliente.php?email=" . urlencode($cliente_email) . "&ticket_id=$ticket_id");
            exit();
        } catch(Exception $e){
            $error = "Error al enviar respuesta";
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
        .input-focus:focus { outline: none; box-shadow: 0 0 0 3px rgba(0, 48, 135, 0.3); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="card rounded-2xl shadow-2xl w-full max-w-4xl border-8 border-blue-900">
        <!-- Header -->
        <div class="text-center pt-12 pb-8 bg-gradient-to-b from-blue-900 to-blue-800 rounded-t-2xl">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-20 mx-auto">
            <h1 class="text-4xl font-bold text-white mt-6">PORTAL DE CLIENTES</h1>
            <p class="text-xl text-blue-200 mt-2">Mis Tickets</p>
        </div>

        <div class="p-10">
            <?php if($error): ?>
            <div class="bg-red-100 border-4 border-red-600 text-red-800 px-8 py-6 rounded-2xl mb-8 text-xl font-bold text-center shadow-lg">
                <i class="fas fa-exclamation-triangle mr-3"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if($mensaje): ?>
            <div class="bg-green-100 border-4 border-green-600 text-green-800 px-8 py-6 rounded-2xl mb-8 text-xl font-bold text-center shadow-lg">
                <i class="fas fa-check-circle mr-3"></i><?= htmlspecialchars($mensaje) ?>
            </div>
            <?php endif; ?>

            <!-- FORMULARIO PARA BUSCAR POR EMAIL -->
            <form method="GET" class="mb-10">
                <div class="flex gap-4 max-w-xl mx-auto">
                    <input type="email" name="email" value="<?= htmlspecialchars($cliente_email) ?>" required 
                           class="flex-1 p-4 border-4 border-blue-300 rounded-xl text-lg focus:border-blue-900 transition shadow-md input-focus"
                           placeholder="tuemail@cliente.com">
                    <button type="submit" 
                            class="bg-blue-900 text-white px-10 py-4 rounded-xl font-bold text-lg hover:bg-blue-800 shadow-md">
                        BUSCAR
                    </button>
                </div>
            </form>

            <?php if(empty($tickets) && !empty($cliente_email)): ?>
            <div class="bg-yellow-100 border-4 border-yellow-600 text-yellow-800 px-8 py-6 rounded-2xl mb-10 text-xl font-bold text-center shadow-lg">
                <i class="fas fa-info-circle mr-3"></i>No se encontraron tickets para este correo
            </div>
            <?php endif; ?>

            <!-- LISTA DE TICKETS -->
            <?php if(!empty($tickets)): ?>
            <div class="space-y-10">
                <?php foreach($tickets as $t): 
                    $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
                    $abierto = $t['id'] == $ticket_id;
                ?>
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-xl p-8 border-l-8 border-blue-900 <?= $abierto ? 'ring-4 ring-blue-400' : '' ?>">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="text-2xl font-bold text-blue-900">#<?= htmlspecialchars($numero) ?></span>
                            <span class="ml-4 px-5 py-2 rounded-full text-sm font-bold <?= $t['estado']=='abierto'?'bg-green-200 text-green-800':($t['estado']=='cerrado'?'bg-gray-400 text-white':'bg-yellow-200 text-yellow-800') ?>">
                                <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                            </span>
                            <span class="ml-3 px-5 py-2 rounded-full text-sm font-bold <?= $t['prioridad']=='urgente'?'bg-red-200 text-red-800':($t['prioridad']=='alta'?'bg-orange-200 text-orange-800':'bg-blue-200 text-blue-800') ?>">
                                <?= ucfirst($t['prioridad']) ?>
                            </span>
                        </div>
                        <small class="text-gray-600 text-sm"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></small>
                    </div>

                    <h3 class="text-xl font-bold text-blue-900 mb-3"><?= htmlspecialchars($t['asunto']) ?></h3>
                    <p class="text-base text-gray-700 mb-5"><?= nl2br(htmlspecialchars(substr($t['mensaje'], 0, 200))) ?><?= strlen($t['mensaje']) > 200 ? '...' : '' ?></p>

                    <div class="flex flex-wrap gap-4 text-sm mb-6">
                        <span><strong>Tipo:</strong> <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?></span>
                        <span><strong>Agente:</strong> <?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?></span>
                    </div>

                    <!-- BOTÓN PARA ABRIR RESPUESTA -->
                    <div class="text-right mb-6">
                        <a href="portal_cliente.php?email=<?= urlencode($cliente_email) ?>&ticket_id=<?= $t['id'] ?>" 
                           class="bg-blue-600 text-white px-8 py-4 rounded-xl font-bold text-base hover:bg-blue-700 shadow-md inline-block">
                            RESPONDER
                        </a>
                    </div>

                    <!-- HISTORIAL DE RESPUESTAS -->
                    <?php 
                    $stmt_resp = $pdo->prepare("SELECT * FROM respuestas WHERE ticket_id = ? ORDER BY creado_el");
                    $stmt_resp->execute([$t['id']]);
                    $respuestas = $stmt_resp->fetchAll();
                    if(!empty($respuestas)): ?>
                    <div class="border-t-4 border-blue-300 pt-6">
                        <h4 class="text-lg font-bold text-blue-900 mb-4">Historial</h4>
                        <div class="space-y-4">
                            <?php foreach($respuestas as $r): ?>
                            <div class="bg-white rounded-lg shadow-md p-5 border-l-6 <?= $r['autor']=='cliente'?'border-green-600':'border-blue-600' ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="font-bold text-base <?= $r['autor']=='cliente'?'text-green-800':'text-blue-800' ?>">
                                        <?= $r['autor']=='cliente'?'Tú (Cliente)':'Agente' ?>
                                    </span>
                                    <small class="text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($r['creado_el'])) ?></small>
                                </div>
                                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($r['mensaje'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FORMULARIO DE RESPUESTA -->
                    <?php if($abierto && $t['estado'] != 'cerrado'): ?>
                    <div class="mt-8 border-t-4 border-green-300 pt-8">
                        <h4 class="text-lg font-bold text-green-800 mb-4">Enviar Respuesta</h4>
                        <form method="POST">
                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                            <textarea name="respuesta" rows="5" required 
                                      class="w-full p-5 border-4 border-green-300 rounded-xl text-base focus:border-green-600 transition resize-none shadow-md"
                                      placeholder="Escribe tu respuesta aquí..."></textarea>
                            <div class="text-right mt-5">
                                <button type="submit" 
                                        class="bg-green-600 text-white px-12 py-5 rounded-xl font-bold text-base hover:bg-green-700 shadow-md">
                                    ENVIAR RESPUESTA
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- BOTÓN CREAR NUEVO TICKET - 40% ANCHO, CENTRADO -->
            <div class="mt-16 text-center">
                <a href="crear_ticket.php" 
                   class="inline-block w-full max-w-xs bg-gradient-to-r from-green-600 to-green-500 text-white px-16 py-8 rounded-full text-xl font-bold hover:from-green-700 hover:to-green-600 shadow-2xl transform hover:scale-105 transition">
                    CREAR NUEVO TICKET
                </a>
            </div>
        </div>
    </div>
</body>
</html>