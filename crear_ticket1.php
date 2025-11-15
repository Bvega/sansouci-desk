<?php
require 'config.php';

// INCLUIR PHPMailer UNA SOLA VEZ
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

$mensaje = '';
$error = '';
$enviado = false; // ← NUEVA VARIABLE PARA CONTROLAR ENVÍO

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $cliente_email = trim($_POST['cliente_email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje_text = trim($_POST['mensaje'] ?? '');
    $tipo_servicio = trim($_POST['tipo_servicio'] ?? 'General');
    
    if(empty($cliente_email) || empty($asunto) || empty($mensaje_text)){
        $error = "Completa todos los campos";
    } else {
        try {
                        // GENERAR NÚMERO DE TICKET ÚNICO Y AUTOMÁTICO
            do {
                $numero = 'TCK-' . date('Y') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("SELECT id FROM tickets WHERE numero = ?");
                $stmt->execute([$numero]);
            } while ($stmt->fetch());
            
                        // INSERTAR TICKET (numero se genera automáticamente)
            $stmt = $pdo->prepare("INSERT INTO tickets (cliente_email, asunto, mensaje, tipo_servicio, estado) 
                                   VALUES (?, ?, ?, ?, 'abierto')");
            $stmt->execute([$cliente_email, $asunto, $mensaje_text, $tipo_servicio]);
            $ticket_id = $pdo->lastInsertId();
            
            // OBTENER NÚMERO GENERADO
            $stmt = $pdo->prepare("SELECT numero FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $numero = $stmt->fetchColumn();
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

            // ENVÍO DE CORREO
            $stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
            $config_email = $stmt->fetch();

            if(!$config_email || empty($config_email['smtp_user'])){
                $mensaje = "Ticket #$numero creado. Configura el correo en Mantenimiento";
            } else {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_user'];
                    $mail->Password   = $config_email['smtp_pass'];
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $config_email['smtp_port'];
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($config_email['smtp_from_email'], $config_email['smtp_from_name']);
                    $mail->addAddress($cliente_email);

                    if($config_email['notificar_usuarios']){
                        $usuarios = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ?");
                        $usuarios->execute([$cliente_email]);
                        foreach($usuarios->fetchAll() as $u){
                            $mail->addBCC($u['email']);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Ticket #$numero - Sansouci Desk";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.15); border-left: 6px solid #003087;'>
                        <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 50px; margin: 0 auto 20px; display: block;'>
                        <h2 style='color: #003087; text-align: center;'>TICKET CREADO</h2>
                        <p style='text-align: center; font-size: 24px;'><strong>N° Ticket:</strong> <span style='color: #003087; font-weight: bold;'>$numero</span></p>
                        <p style='text-align: center;'>Tu solicitud ha sido recibida.</p>
                        <div style='text-align: center; margin: 20px 0;'>
                            <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($cliente_email) . "' 
                               style='background: #003087; color: white; padding: 12px 30px; text-decoration: none; border-radius: 30px; font-weight: bold;'>
                               VER MI TICKET
                            </a>
                        </div>
                        <p style='text-align: center; color: #777; font-size: 13px;'>
                            Sansouci Puerto de Santo Domingo<br>
                            soporte@sansouci.com.do
                        </p>
                    </div>";

                    $mail->send();
                    $mensaje = "¡Ticket #$numero creado con éxito! Revisa tu correo.";
                } catch (Exception $e) {
                    $mensaje = "Ticket #$numero creado. Error de correo: " . $mail->ErrorInfo;
                }
            }

            // REDIRECCIÓN POST-REDIRECT-GET → EVITA DUPLICADOS AL RECARGAR
            $enviado = true;
            header("Location: crear_ticket.php?enviado=1&ticket=$numero");
            exit();

        } catch(Exception $e){
            $error = "Error del sistema: " . $e->getMessage();
        }
    }
}

// MOSTRAR MENSAJE DE ÉXITO DESPUÉS DE REDIRECCIÓN
if(isset($_GET['enviado']) && $_GET['enviado'] == '1'){
    $mensaje = "¡Ticket #" . htmlspecialchars($_GET['ticket']) . " creado con éxito! Revisa tu correo.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sansouci Desk - Nueva Solicitud</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #001f3f 0%, #003087 100%); font-family: 'Segoe UI', sans-serif; }
        .card { backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.97); }
        .input-focus:focus { outline: none; box-shadow: 0 0 0 3px rgba(0, 48, 135, 0.3); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="card rounded-2xl shadow-2xl w-full max-w-lg border border-blue-900">
        <div class="text-center pt-8 pb-6 px-8 bg-gradient-to-b from-blue-900 to-blue-800 rounded-t-2xl">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-16 mx-auto">
            <h1 class="text-2xl font-bold text-white mt-4">SANSOUCI DESK</h1>
            <p class="text-blue-200 text-sm">Portal de Clientes</p>
        </div>

        <div class="p-8">
            <h2 class="text-xl font-bold text-center text-gray-800 mb-6">Crear Nueva Solicitud</h2>
            
            <?php if($mensaje): ?>
            <div class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm text-center">
                <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm text-center">
                <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
            </div>
            <?php endif; ?>

            <?php if(!$enviado): // SOLO MOSTRAR FORMULARIO SI NO SE HA ENVIADO ?>
            <form method="POST" class="space-y-5">
                <div>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-3 text-blue-600 text-lg"></i>
                        <input type="email" name="cliente_email" required 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus text-gray-800"
                               placeholder="tuemail@cliente.com">
                    </div>
                </div>

                <div>
                    <div class="relative">
                        <i class="fas fa-concierge-bell absolute left-3 top-3 text-blue-600 text-lg"></i>
                        <select name="tipo_servicio" required 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus text-gray-800 appearance-none">
                            <option value="">Tipo de Servicio</option>
                            <?php
                            $stmt = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
                            while($row = $stmt->fetch()){
                                echo "<option value='" . htmlspecialchars($row['nombre']) . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="relative">
                        <i class="fas fa-tag absolute left-3 top-3 text-blue-600 text-lg"></i>
                        <input type="text" name="asunto" required 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus text-gray-800"
                               placeholder="Asunto breve">
                    </div>
                </div>

                <div>
                    <div class="relative">
                        <i class="fas fa-comment-dots absolute left-3 top-10 text-blue-600 text-lg"></i>
                        <textarea name="mensaje" rows="4" required 
                                  class="w-full pl-10 pr-4 pt-3 pb-3 border border-gray-300 rounded-lg input-focus text-gray-800 resize-none"
                                  placeholder="Describe tu requerimiento..."></textarea>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-900 to-blue-700 text-white py-4 rounded-lg font-bold text-lg hover:from-blue-800 hover:to-blue-600 transition shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i> ENVIAR SOLICITUD
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-6 text-center text-sm text-gray-600">
                <p>¿Ya tienes un ticket?</p>
                <a href="portal_cliente.php" class="text-blue-700 font-semibold hover:underline">
                    Ver mis tickets →
                </a>
            </div>

            <div class="mt-8 text-center text-xs text-gray-500 border-t pt-4">
                <p>© 2025 Sansouci Puerto de Santo Domingo</p>
            </div>
        </div>
    </div>
</body>
</html>