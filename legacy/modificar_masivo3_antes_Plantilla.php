<?php 
ob_start();
require 'header.php';

$ids = [];
if(isset($_GET['ids'])){
    $ids_raw = explode(',', $_GET['ids']);
    foreach($ids_raw as $id){
        $id = intval($id);
        if($id > 0) $ids[] = $id;
    }
}
if(empty($ids)){
    header('Location: tickets.php');
    exit();
}

// PROCESAR ACTUALIZACIÓN + RESPUESTA
if($_POST){
    $estado = $_POST['estado'] ?? '';
    $prioridad = $_POST['prioridad'] ?? '';
    $tipo_servicio = $_POST['tipo_servicio'] ?? '';
    $agente_id = !empty($_POST['agente_id']) ? intval($_POST['agente_id']) : null;
    $respuesta = trim($_POST['respuesta'] ?? '');

    $in = str_repeat('?,', count($ids) - 1) . '?';
    
    // ACTUALIZAR TICKETS
    $sql = "UPDATE tickets SET estado=?, prioridad=?, tipo_servicio=?";
    $params = [$estado, $prioridad, $tipo_servicio];
    
    if($agente_id !== null){
        $sql .= ", agente_id=?";
        $params[] = $agente_id;
    }
    
    $sql .= " WHERE id IN ($in)";
    $params = array_merge($params, $ids);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // AGREGAR RESPUESTA A CADA TICKET + ENVÍO DE CORREO
    if(!empty($respuesta)){
        $stmt_resp = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                                    VALUES (?, ?, 'agente', ?, NOW())");

        // CARGAR CONFIG EMAIL
        $stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
        $config_email = $stmt->fetch();

        if($config_email && !empty($config_email['smtp_usuario'])){
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
                $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?? '';
                $mail->Port       = $config_email['smtp_port'];
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom($config_email['smtp_from_email'], $config_email['smtp_from_name']);

                // CARGAR CLIENTES DE LOS TICKETS
                $in = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("SELECT cliente_email FROM tickets WHERE id IN ($in)");
                $stmt->execute($ids);
                $clientes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($clientes as $cliente_email) {
                    $mail->addAddress($cliente_email);
                }

                // NOTIFICACIONES EN BCC
                $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                foreach ($destinos as $to) {
                    if($to && !in_array($to, $clientes)){
                        $mail->addBCC($to);
                    }
                }

                $mail->isHTML(true);
                $mail->Subject = "Actualización masiva de tickets - Sansouci Desk";
                $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                    <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                    <h1 style='color: #003087; text-align: center; font-size: 32px;'>ACTUALIZACIÓN MASIVA</h1>
                    <p style='font-size: 20px; text-align: center; color: #333;'>
                        Tus tickets han sido actualizados por el equipo de soporte.
                    </p>
                    <div style='background: #e6f7ff; padding: 30px; border-radius: 15px; margin: 30px 0; border-left: 8px solid #003087;'>
                        <p style='font-size: 20px; color: #003087; font-weight: bold; margin-bottom: 20px;'>Mensaje del agente:</p>
                        <p style='font-size: 18px; color: #333; line-height: 1.8;'>" . nl2br(htmlspecialchars($respuesta)) . "</p>
                    </div>
                    <div style='text-align: center; margin: 40px 0;'>
                        <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($user['email']) . "' 
                           style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                           VER MIS TICKETS
                        </a>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                // Silencioso
            }
        }

        foreach($ids as $id){
            $stmt_resp->execute([$id, $respuesta, $user['email']]);
        }
    }

    header("Location: tickets.php?msg=" . urlencode(count($ids) . " tickets actualizados"));
    exit();
}

// === CARGAR DATOS (igual que antes) ===
$agentes = $pdo->query("SELECT id, nombre FROM users WHERE rol = 'agente' ORDER BY nombre")->fetchAll();
$tipos = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre")->fetchAll();

$in = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id IN ($in)");
$stmt->execute($ids);
$tickets_seleccionados = $stmt->fetchAll();
?>

<!-- TU HTML ÉPICO (mismo que antes) -->
<!-- ... (todo tu diseño) ... -->

<?php 
ob_end_flush();
require 'footer.php'; 
?>