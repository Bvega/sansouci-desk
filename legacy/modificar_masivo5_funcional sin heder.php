<?php 
ob_start();
require 'config.php';
require 'header.php';

// === VERIFICAR USUARIO ===
if(!isset($user) || !is_array($user) || empty($user['email'])) {
    header("Location: login.php");
    exit();
}

// === OBTENER IDs SELECCIONADOS ===
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

// === CARGAR VALORES ACTUALES (PARA PRESELECCIONAR) ===
$valores_actuales = [
    'estado' => '',
    'prioridad' => '',
    'tipo_servicio' => '',
    'agente_id' => ''
];

if(count($ids) === 1){
    // Si es solo un ticket, cargar sus valores
    $stmt = $pdo->prepare("SELECT estado, prioridad, tipo_servicio, agente_id FROM tickets WHERE id = ?");
    $stmt->execute([$ids[0]]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if($actual){
        $valores_actuales = $actual;
    }
} else {
    // Si son varios, detectar si todos tienen el mismo valor
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT estado, prioridad, tipo_servicio, agente_id FROM tickets WHERE id IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $primer = $rows[0] ?? null;
    if($primer){
        $todos_iguales = true;
        foreach($rows as $row){
            if($row['estado'] !== $primer['estado'] || 
               $row['prioridad'] !== $primer['prioridad'] || 
               $row['tipo_servicio'] !== $primer['tipo_servicio'] || 
               $row['agente_id'] !== $primer['agente_id']){
                $todos_iguales = false;
                break;
            }
        }
        if($todos_iguales){
            $valores_actuales = $primer;
        }
    }
}

// === CARGAR CONFIG EMAIL ===
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

// === PROCESAR ACTUALIZACIÓN ===
if($_POST){
    $estado = $_POST['estado'] ?? '';
    $prioridad = $_POST['prioridad'] ?? '';
    $tipo_servicio = $_POST['tipo_servicio'] ?? '';
    $agente_id = !empty($_POST['agente_id']) ? intval($_POST['agente_id']) : null;
    $respuesta = trim($_POST['respuesta'] ?? '');

    $in = str_repeat('?,', count($ids) - 1) . '?';
    
    $sql = "UPDATE tickets SET estado=?, prioridad=?, tipo_servicio=?";
    $params = [$estado, $prioridad, $tipo_servicio];
    
    if($agente_id !== null){
        $sql .= ", agente_id=?";
        $params[] = $agente_id;
    }
    
    $sql .= " WHERE id IN ($in)";
    $params = array_merge($params, $ids);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch(Exception $e) {
        $error = "Error al actualizar: " . htmlspecialchars($e->getMessage());
    }

    if(!empty($respuesta)){
        $stmt_resp = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                                    VALUES (?, ?, 'agente', ?, NOW())");

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

                $in = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("SELECT DISTINCT cliente_email FROM tickets WHERE id IN ($in)");
                $stmt->execute($ids);
                $clientes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($clientes as $cliente_email) {
                    $mail->addAddress($cliente_email);
                }

                $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                foreach ($destinos as $to) {
                    if($to && !in_array($to, $clientes)){
                        $mail->addBCC($to);
                    }
                }

                $mail->isHTML(true);
                $mail->Subject = "Actualización masiva de tickets - Sansouci Desk";
                $mail->Body = "
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
                        <a href='http://localhost/sansouci-desk/portal_cliente.php?email=[EMAIL]' 
                           style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                           VER MIS TICKETS
                        </a>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $e) {}
        }

        foreach($ids as $id){
            try {
                $stmt_resp->execute([$id, $respuesta, $user['email']]);
            } catch(Exception $e) {}
        }
    }

    header("Location: tickets.php?msg=" . urlencode(count($ids) . " tickets actualizados"));
    exit();
}

// === CARGAR DATOS ===
try {
    $agentes = $pdo->query("SELECT id, nombre FROM users WHERE rol IN ('agente','administrador') ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $tipos = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    $agentes = [];
    $tipos = [];
    $error = "Error cargando datos: " . htmlspecialchars($e->getMessage());
}

$in = str_repeat('?,', count($ids) - 1) . '?';
try {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id IN ($in)");
    $stmt->execute($ids);
    $tickets_seleccionados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $tickets_seleccionados = [];
    $error = "Error cargando tickets: " . htmlspecialchars($e->getMessage());
}
?>

<div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-5xl font-bold text-white text-center mb-12 drop-shadow-2xl">
            MODIFICAR MASIVO - <?= count($ids) ?> TICKETS
        </h1>

        <?php if(isset($error)): ?>
        <div class="bg-red-600 text-white px-10 py-6 rounded-3xl mb-10 text-xl font-bold text-center shadow-2xl">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-3xl p-10 border-8 border-blue-900">
            <form method="POST" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-lg font-bold text-blue-900 mb-2">Estado</label>
                        <select name="estado" class="w-full p-4 border-4 border-blue-300 rounded-xl text-base font-bold bg-gradient-to-r from-blue-50 to-blue-100">
                            <option value="">-- No cambiar --</option>
                            <option value="abierto" <?= $valores_actuales['estado']=='abierto'?'selected':'' ?>>Abierto</option>
                            <option value="en_proceso" <?= $valores_actuales['estado']=='en_proceso'?'selected':'' ?>>En Proceso</option>
                            <option value="cerrado" <?= $valores_actuales['estado']=='cerrado'?'selected':'' ?>>Cerrado</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-lg font-bold text-blue-900 mb-2">Prioridad</label>
                        <select name="prioridad" class="w-full p-4 border-4 border-blue-300 rounded-xl text-base font-bold bg-gradient-to-r from-orange-50 to-orange-100">
                            <option value="">-- No cambiar --</option>
                            <option value="baja" <?= $valores_actuales['prioridad']=='baja'?'selected':'' ?>>Baja</option>
                            <option value="media" <?= $valores_actuales['prioridad']=='media'?'selected':'' ?>>Media</option>
                            <option value="alta" <?= $valores_actuales['prioridad']=='alta'?'selected':'' ?>>Alta</option>
                            <option value="urgente" <?= $valores_actuales['prioridad']=='urgente'?'selected':'' ?>>Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-lg font-bold text-blue-900 mb-2">Tipo de Servicio</label>
                        <select name="tipo_servicio" class="w-full p-4 border-4 border-blue-300 rounded-xl text-base font-bold bg-gradient-to-r from-purple-50 to-purple-100">
                            <option value="">-- No cambiar --</option>
                            <?php foreach($tipos as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>" <?= $valores_actuales['tipo_servicio']==$tipo?'selected':'' ?>>
                                <?= htmlspecialchars($tipo) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-lg font-bold text-blue-900 mb-2">Asignar Agente</label>
                        <select name="agente_id" class="w-full p-4 border-4 border-blue-300 rounded-xl text-base font-bold bg-gradient-to-r from-green-50 to-green-100">
                            <option value="">-- No cambiar --</option>
                            <?php foreach($agentes as $agente): ?>
                            <option value="<?= $agente['id'] ?>" <?= $valores_actuales['agente_id']==$agente['id']?'selected':'' ?>>
                                <?= htmlspecialchars($agente['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-lg font-bold text-blue-900 mb-2">Mensaje (se enviará a todos los clientes)</label>
                    <textarea name="respuesta" rows="6" 
                              class="w-full p-6 border-4 border-blue-400 rounded-2xl text-base focus:border-green-600 transition resize-none shadow-lg"
                              placeholder="Este mensaje se enviará a todos los clientes seleccionados..."></textarea>
                </div>

                <div class="text-center space-y-6 md:space-y-0 md:space-x-10">
                    <button type="submit" 
                            class="inline-block w-full max-w-xs bg-gradient-to-r from-green-600 to-green-500 text-white px-20 py-8 rounded-full text-xl font-bold hover:from-green-700 hover:to-green-600 shadow-2xl transform hover:scale-105 transition">
                        ACTUALIZAR TICKETS
                    </button>
                    <a href="tickets.php" 
                       class="inline-block w-full max-w-xs bg-gray-600 text-white px-20 py-8 rounded-full text-xl font-bold hover:bg-gray-700 shadow-2xl">
                        CANCELAR
                    </a>
                </div>
            </form>
        </div>

        <div class="mt-12 bg-white rounded-3xl shadow-3xl p-8 border-8 border-blue-900">
            <h3 class="text-2xl font-bold text-blue-900 mb-6">Tickets seleccionados (<?= count($tickets_seleccionados) ?>):</h3>
            <div class="space-y-4">
                <?php foreach($tickets_seleccionados as $t): 
                    $num = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
                ?>
                <div class="bg-blue-50 p-4 rounded-xl border-2 border-blue-300">
                    <span class="font-bold">#<?= htmlspecialchars($num) ?></span> - 
                    <?= htmlspecialchars($t['asunto']) ?> 
                    (<?= htmlspecialchars($t['cliente_email']) ?>)
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center mt-12">
            <a href="tickets.php" class="text-blue-300 hover:text-white text-xl underline">
                Volver a Tickets
            </a>
        </div>
    </div>
</div>

<?php 
ob_end_flush();
require 'footer.php'; 
?>