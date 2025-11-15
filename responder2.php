<?php 
ob_start();
require 'config.php';
require 'header.php'; // ← CARGA LA SESIÓN Y $user

// === VERIFICAR QUE $user EXISTE (SIN MOSTRAR ERROR AL USUARIO) ===
if(!isset($user) || !is_array($user) || empty($user['email'])) {
    // Si no hay usuario, redirigir al login sin mostrar error feo
    header("Location: login.php");
    exit();
}

if(!isset($_GET['ticket_id']) || !is_numeric($_GET['ticket_id'])){
    die('<div class="p-20 text-center text-red-600 text-5xl font-bold bg-white rounded-3xl shadow-3xl">Ticket no válido</div>');
}

$ticket_id = intval($_GET['ticket_id']);

// CARGAR TICKET
$stmt = $pdo->prepare("SELECT t.*, u.nombre as agente_nombre FROM tickets t LEFT JOIN users u ON t.agente_id = u.id WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if(!$ticket){
    die('<div class="p-20 text-center text-red-600 text-5xl font-bold bg-white rounded-3xl shadow-3xl">Ticket no encontrado</div>');
}

$numero = $ticket['numero'] ?? 'TCK-'.str_pad($ticket['id'],5,'0',STR_PAD_LEFT);

// CARGAR RESPUESTAS
$stmt = $pdo->prepare("SELECT * FROM respuestas WHERE ticket_id = ? ORDER BY creado_el");
$stmt->execute([$ticket_id]);
$respuestas = $stmt->fetchAll();

// === CARGAR CONFIG EMAIL (100% SEGURO) ===
$config_email = [
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_usuario' => '',
    'smtp_clave' => '',
    'smtp_encriptacion' => 'tls',
    'smtp_from_email' => 'soporte@sansouci.com.do',
    'smtp_from_name' => 'Sansouci Desk',
    'correos_notificacion' => '',
    'activado' => 0
];

try {
    $stmt = $pdo->query("SELECT * FROM config_email WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(is_array($row)){
        $config_email = array_merge($config_email, $row);
    }
} catch(Exception $e) {}

// === PROCESAR RESPUESTA ===
$mensaje = '';
if($_POST && isset($_POST['respuesta'])){
    $respuesta = trim($_POST['respuesta']);
    if(empty($respuesta)){
        $mensaje = "La respuesta no puede estar vacía";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                                   VALUES (?, ?, 'agente', ?, NOW())");
            $stmt->execute([$ticket_id, $respuesta, $user['email']]);

            if(!empty($config_email['smtp_usuario']) && !empty($config_email['smtp_clave'])){
                require 'phpmailer/src/Exception.php';
                require 'phpmailer/src/PHPMailer.php';
                require 'phpmailer/src/SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_usuario'];
                    $mail->Password   = $config_email['smtp_clave'];
                    $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?: false;
                    $mail->Port       = (int)$config_email['smtp_port'];
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($config_email['smtp_usuario'], $config_email['smtp_from_name']);
                    $mail->addReplyTo($config_email['smtp_from_email'] ?: $config_email['smtp_usuario'], 'Sansouci Desk');
                    $mail->addAddress($ticket['cliente_email']);

                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                    foreach ($destinos as $to) {
                        if($to && $to != $ticket['cliente_email']){
                            $mail->addBCC($to);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Re: Ticket #$numero - {$ticket['asunto']}";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                        <h1 style='color: #003087; text-align: center; font-size: 32px;'>RESPUESTA A TU TICKET</h1>
                        <div style='background: #f0f8ff; padding: 30px; border-radius: 15px; text-align: center; border: 3px dashed #003087;'>
                            <p style='font-size: 28px; margin: 15px 0;'><strong>Ticket:</strong> <span style='color: #003087; font-size: 40px; font-weight: bold;'>$numero</span></p>
                        </div>
                        <div style='background: #e6f7ff; padding: 30px; border-radius: 15px; margin: 30px 0; border-left: 8px solid #003087;'>
                            <p style='font-size: 20px; color: #003087; font-weight: bold; margin-bottom: 20px;'>Respuesta del agente:</p>
                            <p style='font-size: 18px; color: #333; line-height: 1.8;'>" . nl2br(htmlspecialchars($respuesta)) . "</p>
                        </div>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($ticket['cliente_email']) . "' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                               VER MI TICKET
                            </a>
                        </div>
                    </div>";

                    $mail->send();
                    $mensaje = "Respuesta enviada al cliente y notificaciones";
                } catch (Exception $e) {
                    $mensaje = "Respuesta guardada. Error de correo: " . htmlspecialchars($mail->ErrorInfo);
                }
            } else {
                $mensaje = "Respuesta guardada. Configura el correo en Mantenimiento → Config. Email";
            }

            header("Location: responder.php?ticket_id=$ticket_id&enviado=1");
            exit();

        } catch(Exception $e){
            $mensaje = "Error del sistema: " . htmlspecialchars($e->getMessage());
        }
    }
}

$mensaje_exito = (isset($_GET['enviado']) && $_GET['enviado'] == 1);
?>

<!-- TU HTML COMPLETO CON PLANTILLAS RÁPIDAS -->
<div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 py-16 px-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-7xl font-bold text-white text-center mb-20 drop-shadow-2xl">
            Ticket #<?= htmlspecialchars($numero) ?> - Responder
        </h1>

        <?php if($mensaje_exito): ?>
        <div class="bg-green-600 text-white px-20 py-16 rounded-3xl mb-20 text-5xl font-bold text-center shadow-3xl">
            RESPUESTA ENVIADA CORRECTAMENTE
        </div>
        <?php endif; ?>

        <?php if($mensaje && !$mensaje_exito): ?>
        <div class="bg-yellow-100 border-12 border-yellow-600 text-yellow-800 px-20 py-16 rounded-3xl mb-20 text-4xl font-bold text-center shadow-3xl">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <!-- INFO DEL TICKET -->
        <div class="bg-white rounded-3xl shadow-3xl p-20 mb-20 border-12 border-blue-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-20 text-4xl">
                <div>
                    <p><strong>Cliente:</strong> <?= htmlspecialchars($ticket['cliente_email']) ?></p>
                    <p><strong>Asunto:</strong> <?= htmlspecialchars($ticket['asunto']) ?></p>
                    <p><strong>Tipo:</strong> <?= htmlspecialchars($ticket['tipo_servicio'] ?? 'General') ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="px-12 py-6 rounded-full font-bold <?= $ticket['estado']=='abierto'?'bg-green-200 text-green-800':($ticket['estado']=='cerrado'?'bg-gray-400 text-white':'bg-yellow-200 text-yellow-800') ?>">
                            <?= ucfirst(str_replace('_',' ',$ticket['estado'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p><strong>Agente:</strong> <?= htmlspecialchars($ticket['agente_nombre'] ?? 'Sin asignar') ?></p>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($ticket['creado_el'])) ?></p>
                    <p><strong>Prioridad:</strong> 
                        <span class="px-12 py-6 rounded-full font-bold <?= $ticket['prioridad']=='urgente'?'bg-red-200 text-red-800':($ticket['prioridad']=='alta'?'bg-orange-200 text-orange-800':'bg-blue-200 text-blue-800') ?>">
                            <?= ucfirst($ticket['prioridad']) ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="mt-20 bg-blue-50 p-16 rounded-3xl border-8 border-blue-300">
                <p class="text-4xl font-bold text-blue-900 mb-12">Mensaje original del cliente:</p>
                <p class="text-3xl text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($ticket['mensaje'])) ?></p>
            </div>
        </div>

        <!-- PLANTILLAS RÁPIDAS -->
        <div class="bg-gradient-to-r from-teal-50 to-green-50 rounded-3xl p-16 mb-20 border-12 border-teal-400 shadow-3xl">
            <h4 class="text-5xl font-bold text-teal-900 mb-12 text-center drop-shadow-lg">
                RESPUESTAS RÁPIDAS
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-10">
                <?php
                try {
                    $stmt = $pdo->query("SELECT * FROM plantillas_respuesta ORDER BY titulo");
                    while($p = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <button type="button" 
                        onclick="aplicarPlantilla(`<?= htmlspecialchars(addslashes($p['mensaje']), ENT_QUOTES) ?>`)" 
                        class="bg-white border-8 border-teal-500 rounded-3xl p-12 text-2xl font-bold text-teal-800 hover:bg-teal-100 hover:border-teal-700 hover:scale-110 transition-all duration-300 shadow-2xl transform-gpu">
                    <?= htmlspecialchars($p['titulo']) ?>
                </button>
                <?php 
                    endwhile;
                } catch(Exception $e) {
                    echo "<p class='text-red-600 text-center col-span-3'>No hay plantillas disponibles</p>";
                }
                ?>
            </div>
        </div>

        <!-- FORMULARIO DE RESPUESTA -->
        <form method="POST" class="bg-white rounded-3xl shadow-3xl p-24 border-12 border-green-600">
            <label class="block text-6xl font-bold text-blue-900 mb-16 text-center">
                ESCRIBE TU RESPUESTA
            </label>
            <textarea name="respuesta" rows="16" required 
                      class="w-full p-20 border-12 border-blue-400 rounded-3xl text-4xl focus:border-green-600 transition resize-none shadow-2xl bg-gradient-to-b from-white to-green-50"
                      placeholder="Tu respuesta será enviada al cliente y a los correos de notificación..."></textarea>
            
            <div class="text-center mt-32 space-x-32">
                <button type="submit" 
                        class="bg-gradient-to-r from-green-600 to-green-500 text-white px-96 py-28 rounded-full text-8xl font-bold hover:from-green-700 hover:to-green-600 shadow-3xl transform hover:scale-110 transition">
                    ENVIAR RESPUESTA
                </button>
                <a href="tickets.php" 
                   class="bg-gray-600 text-white px-80 py-28 rounded-full text-8xl font-bold hover:bg-gray-700 inline-block">
                    VOLVER
                </a>
            </div>
        </form>

        <!-- SCRIPT PARA PLANTILLAS -->
        <script>
        function aplicarPlantilla(mensaje) {
            const textarea = document.querySelector('textarea[name="respuesta"]');
            textarea.value = mensaje.replace(/\\n/g, '\n');
            textarea.focus();
            textarea.scrollTop = 0;
        }
        </script>

        <!-- HISTORIAL -->
        <?php if(!empty($respuestas)): ?>
        <div class="mt-40">
            <h2 class="text-7xl font-bold text-white text-center mb-24 drop-shadow-2xl">Historial de Conversación</h2>
            <div class="space-y-20">
                <?php foreach($respuestas as $r): ?>
                <div class="bg-white rounded-3xl shadow-3xl p-20 border-l-20 <?= $r['autor']=='cliente'?'border-green-600':'border-blue-600' ?>">
                    <div class="flex justify-between items-start mb-16">
                        <span class="text-6xl font-bold <?= $r['autor']=='cliente'?'text-green-800':'text-blue-800' ?>">
                            <?= $r['autor']=='cliente'?'Cliente':'Agente' ?> 
                            <?= $r['autor']=='agente' ? '('.htmlspecialchars($r['autor_email']).')' : '' ?>
                        </span>
                        <span class="text-4xl text-gray-600"><?= date('d/m/Y H:i', strtotime($r['creado_el'])) ?></span>
                    </div>
                    <p class="text-4xl text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($r['mensaje'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
ob_end_flush();
require 'footer.php'; 
?>