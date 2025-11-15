<?php 
ob_start();
require 'config.php';

// INCLUIR PHPMailer
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

$mensaje = '';
$error = '';
$ticket_creado = false;
$numero_ticket = '';

// === CARGAR CONFIG EMAIL CON VALORES POR DEFECTO ===
$stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
$config_row = $stmt->fetch(PDO::FETCH_ASSOC);

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

if(is_array($config_row)){
    $config_email = array_merge($config_email, $config_row);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $cliente_email = trim($_POST['cliente_email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje_text = trim($_POST['mensaje'] ?? '');
    $tipo_servicio = trim($_POST['tipo_servicio'] ?? 'General');
    
    if(empty($cliente_email) || empty($asunto) || empty($mensaje_text)){
        $error = "Todos los campos son obligatorios";
    } else {
        try {
            // GENERAR NÚMERO DE TICKET
            $stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
            $count = $stmt->fetchColumn();
            $numero = 'TCK-' . date('Y') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            
            // INSERTAR TICKET
            $stmt = $pdo->prepare("INSERT INTO tickets (numero, cliente_email, asunto, mensaje, tipo_servicio, estado) 
                                   VALUES (?, ?, ?, ?, ?, 'abierto')");
            $stmt->execute([$numero, $cliente_email, $asunto, $mensaje_text, $tipo_servicio]);
            $ticket_id = $pdo->lastInsertId();
            // === ASIGNACIÓN AUTOMÁTICA ===
            $stmt = $pdo->query("SELECT activa, modo FROM config_asignacion LIMIT 1");
            $config_asig = $stmt->fetch();

            if($config_asig && $config_asig['activa']){
                $agente_id = null;

                // 1. POR CLIENTE ESPECÍFICO
                $stmt = $pdo->prepare("SELECT agente_id FROM asignacion_clientes WHERE cliente_email = ?");
                $stmt->execute([$cliente_email]);
                $asig = $stmt->fetch();
                if($asig){
                    $agente_id = $asig['agente_id'];
                } else {
                    // 2. POR CARGA DE TRABAJO O ROUND ROBIN
                    if($config_asig['modo'] == 'carga_trabajo'){
                        $stmt = $pdo->query("SELECT id FROM users WHERE rol = 'agente' 
                                             ORDER BY (SELECT COUNT(*) FROM tickets WHERE agente_id = users.id AND estado != 'cerrado') ASC, id ASC 
                                             LIMIT 1");
                    } else { // round_robin
                        $stmt = $pdo->query("SELECT id FROM users WHERE rol = 'agente' ORDER BY id ASC LIMIT 1");
                    }
                    $agente = $stmt->fetch();
                    $agente_id = $agente['id'] ?? null;
                }

                if($agente_id){
                    $pdo->prepare("UPDATE tickets SET agente_id = ? WHERE id = ?")->execute([$agente_id, $ticket_id]);
                }
            }
            // INSERTAR RESPUESTA DEL CLIENTE
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email) 
                                   VALUES (?, ?, 'cliente', ?)");
            $stmt->execute([$ticket_id, $mensaje_text, $cliente_email]);

            // === ENVÍO DE CORREO ===
            if(!empty($config_email['smtp_usuario']) && !empty($config_email['smtp_clave'])){
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
                    $mail->addAddress($cliente_email);

                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'])));
                    foreach ($destinos as $to) {
                        if($to && $to != $cliente_email){
                            $mail->addBCC($to);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Nuevo Ticket #$numero - Sansouci Desk";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                        <h1 style='color: #003087; text-align: center; font-size: 32px; margin-bottom: 30px;'>TICKET CREADO</h1>
                        <div style='background: #f0f8ff; padding: 30px; border-radius: 15px; text-align: center; border: 3px dashed #003087;'>
                            <p style='font-size: 28px; margin: 15px 0;'><strong>Ticket:</strong> <span style='color: #003087; font-size: 40px; font-weight: bold;'>$numero</span></p>
                            <p style='font-size: 22px; margin: 15px 0;'><strong>Tipo de Servicio:</strong> " . htmlspecialchars($tipo_servicio) . "</p>
                            <p style='font-size: 22px; margin: 15px 0;'><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>
                        </div>
                        <p style='font-size: 20px; text-align: center; margin: 40px 四十px; color: #333;'>
                            Tu solicitud ha sido recibida y será atendada en menos de <strong>2 horas hábiles</strong>.
                        </p>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($cliente_email) . "' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block; box-shadow: 0 10px 30px rgba(0,48,135,0.4);'>
                               VER MI TICKET
                            </a>
                        </div>
                    </div>";

                    $mail->send();
                    $mensaje = "¡Ticket #$numero creado con éxito! Notificación enviada a todos.";
                } catch (Exception $e) {
                    $mensaje = "Ticket #$numero creado. Error de correo: " . htmlspecialchars($mail->ErrorInfo);
                }
            } else {
                $mensaje = "Ticket #$numero creado. Configura el correo en Manten 双 → Config. Email";
            }

            $ticket_creado = true;
            $numero_ticket = $numero;

            // REDIRECCIÓN PARA EVITAR DUPLICADOS
            header("Location: crear_ticket.php?exito=1&ticket=$numero");
            exit();

        } catch(Exception $e){
            $error = "Error del sistema: " . htmlspecialchars($e->getMessage());
        }
    }
}

// MOSTRAR MENSAJE DE ÉXITO DESDE URL
$exito = (isset($_GET['exito']) && $_GET['exito'] == 1);
$numero_ticket = $_GET['ticket'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - Sansouci Desk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #001f3f 0%, #003087 100%); }
        .card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.98); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="card rounded-3xl shadow-3xl w-full max-w-4xl border-8 border-blue-900">
        <div class="text-center pt-16 pb-10">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-24 mx-auto">
            <h1 class="text-5xl font-bold text-blue-900 mt-8">PORTAL DE CLIENTES</h1>
            <p class="text-2xl text-blue-700 mt-4">Crear Nueva Solicitud</p>
        </div>

        <div class="px-12 pb-16">
            <?php if($exito): ?>
            <div class="bg-green-100 border-8 border-green-600 text-green-800 px-12 py-16 rounded-3xl mb-12 text-3xl font-bold text-center shadow-2xl">
                <i class="fas fa-check-circle text-8xl mb-8 block"></i>
                ¡TICKET CREADO CON ÉXITO!
                <div class="mt-8 text-5xl text-blue-900 font-bold">
                    #<?= htmlspecialchars($numero_ticket) ?>
                </div>
                <div class="mt-12 flex flex-col sm:flex-row justify-center gap-8">
                    <a href="crear_ticket.php" 
                       class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-32 py-10 rounded-full text-2xl font-bold hover:from-blue-700 hover:to-blue-600 shadow-2xl transform hover:scale-105 transition">
                        CREAR NUEVO TICKET
                    </a>
                    <a href="portal_cliente.php" 
                       class="bg-gradient-to-r from-purple-600 to-purple-500 text-white px-32 py-10 rounded-full text-2xl font-bold hover:from-purple-700 hover:to-purple-600 shadow-2xl transform hover:scale-105 transition">
                        VERIFICAR TICKET
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="bg-red-100 border-8 border-red-600 text-red-800 px-12 py-10 rounded-3xl mb-10 text-2xl font-bold text-center shadow-2xl">
                <i class="fas fa-times-circle text-6xl mr-4"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if(!$exito): ?>
            <form method="POST" class="space-y-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div>
                        <label class="block text-xl font-bold text-blue-900 mb-3">
                            Tu Correo
                        </label>
                        <input type="email" name="cliente_email" required 
                               class="w-full p-5 border-4 border-blue-300 rounded-2xl text-lg focus:border-blue-900 transition shadow-lg"
                               placeholder="tucorreo@cliente.com">
                    </div>
                    <div>
                        <label class="block text-xl font-bold text-blue-900 mb-3">
                            Tipo de Servicio
                        </label>
                        <select name="tipo_servicio" required class="w-full p-5 border-4 border-blue-300 rounded-2xl text-lg font-bold bg-gradient-to-r from-purple-50 to-purple-100">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
                                while($row = $stmt->fetch()){
                                    echo "<option value='" . htmlspecialchars($row['nombre']) . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                                }
                            } catch(Exception $e) {
                                echo "<option value='General'>General</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xl font-bold text-blue-900 mb-3">
                        Asunto
                    </label>
                    <input type="text" name="asunto" required 
                           class="w-full p-5 border-4 border-blue-300 rounded-2xl text-lg focus:border-blue-900 transition shadow-lg"
                           placeholder="Resumen de tu solicitud">
                </div>

                <div>
                    <label class="block text-xl font-bold text-blue-900 mb-3">
                        Detalles
                    </label>
                    <textarea name="mensaje" rows="8" required 
                              class="w-full p-5 border-4 border-blue-300 rounded-2xl text-lg focus:border-blue-900 transition resize-none shadow-lg"
                              placeholder="Describe con detalle tu requerimiento..."></textarea>
                </div>

                <div class="text-center pt-10">
                    <button type="submit" 
                            class="w-full max-w-md bg-gradient-to-r from-green-600 to-green-500 text-white px-20 py-8 rounded-full text-2xl font-bold hover:from-green-700 hover:to-green-600 shadow-2xl transform hover:scale-105 transition">
                        ENVIAR SOLICITUD
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <div class="mt-20 text-center">
                <p class="text-xl text-gray-600">¿Ya tienes un ticket?</p>
                <a href="portal_cliente.php" class="text-blue-900 font-bold text-2xl hover:underline mt-4 inline-block">
                    Ver mis tickets existentes
                </a>
            </div>
        </div>
    </div>
</body>
</html>

<?php ob_end_flush(); ?>