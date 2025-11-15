<?php 
ob_start();
require 'config.php';
require 'header.php';

if(!in_array($user['rol'],['administrador','superadmin'])) { 
    die('<div class="p-10 text-center text-red-600 text-3xl font-bold">Acceso denegado. Solo administradores.</div>'); 
}

use PHPMailer\PHPMailer\PHPMailer;
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

// === GUARDAR CONFIGURACIÓN (SIEMPRE ACTUALIZA ID=1) ===
if (isset($_POST['guardar'])) {
    $datos = [
        trim($_POST['smtp_host'] ?? ''),
        (int)($_POST['smtp_port'] ?? 587),
        trim($_POST['smtp_usuario'] ?? ''),
        $_POST['smtp_clave'] ?? '',
        $_POST['smtp_encriptacion'] ?? '',
        trim($_POST['correo_from'] ?? ''),
        trim($_POST['nombre_from'] ?? 'Sansouci Desk'),
        trim($_POST['correos_notificacion'] ?? ''),
        isset($_POST['activado']) ? 1 : 0
    ];

    try {
        // SIEMPRE ACTUALIZA EL REGISTRO CON ID=1
        $sql = "UPDATE config_email SET 
                smtp_host=?, smtp_port=?, smtp_usuario=?, smtp_clave=?, 
                smtp_encriptacion=?, correo_from=?, nombre_from=?, 
                correos_notificacion=?, activado=? 
                WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($datos);
        
        // Si no existía, lo crea
        if($stmt->rowCount() == 0) {
            $sql_insert = "INSERT INTO config_email 
                (id, smtp_host, smtp_port, smtp_usuario, smtp_clave, smtp_encriptacion, correo_from, nombre_from, correos_notificacion, activado) 
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_insert)->execute($datos);
        }

        header("Location: config_correo.php?guardado=1");
        exit();
    } catch(Exception $e){
        $error_msg = "ERROR: " . $e->getMessage();
    }
}

// === PRUEBA DE ENVÍO ===
// (igual que antes, sin cambios)
if (isset($_POST['probar'])) {
    $test_email = trim($_POST['test_email'] ?? '');
    if(empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)){
        header("Location: config_correo.php?prueba=error_correo");
        exit();
    }

    $config = $pdo->query("SELECT * FROM config_email WHERE id = 1")->fetch();
    
    if (!$config || !$config['activado']) {
        header("Location: config_correo.php?prueba=desactivado");
        exit();
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_usuario'];
        $mail->Password   = $config['smtp_clave'];
        $mail->SMTPSecure = $config['smtp_encriptacion'] ?: false;
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['correo_from'], $config['nombre_from']);
        $mail->addAddress($test_email);
        $mail->isHTML(true);
        $mail->Subject = "PRUEBA - Sansouci Desk";
        $mail->Body    = "<h2>PRUEBA EXITOSA</h2><p>Funciona perfecto!</p>";

        $mail->send();
        header("Location: config_correo.php?prueba=enviado");
        exit();
    } catch (Exception $e) {
        header("Location: config_correo.php?prueba=error&msg=" . urlencode($mail->ErrorInfo));
        exit();
    }
}

// === CARGAR CONFIGURACIÓN (SIEMPRE ID=1) ===
$config = $pdo->query("SELECT * FROM config_email WHERE id = 1")->fetch();
if (!$config) {
    $pdo->exec("INSERT INTO config_email (id, activado) VALUES (1, 1)");
    $config = $pdo->query("SELECT * FROM config_email WHERE id = 1")->fetch();
}
?>

<!-- TU DISEÑO ÉPICO (mismo que antes) -->
<div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <h1 class="text-6xl font-bold text-white mb-4">CONFIGURACIÓN DE CORREO</h1>
            <p class="text-2xl text-blue-200">Sansouci Desk - Envío de notificaciones</p>
        </div>

        <!-- MENSAJES DE ÉXITO/ERROR -->
        <?php if(isset($_GET['guardado'])): ?>
        <div class="bg-green-600 text-white px-10 py-6 rounded-3xl mb-10 text-2xl font-bold text-center shadow-2xl">
            CONFIGURACIÓN ACTUALIZADA CORRECTAMENTE
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['prueba']) && $_GET['prueba'] == 'enviado'): ?>
        <div class="bg-green-600 text-white px-10 py-6 rounded-3xl mb-10 text-2xl font-bold text-center shadow-2xl">
            PRUEBA ENVIADA CORRECTAMENTE
        </div>
        <?php endif; ?>

        <!-- FORMULARIO (igual que antes) -->
        <div class="bg-white rounded-3xl shadow-3xl p-12 border-8 border-blue-900">
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <!-- TODOS LOS CAMPOS -->
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Puerto</label>
                    <input type="number" name="smtp_port" value="<?= $config['smtp_port'] ?? 587 ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Encriptación</label>
                    <select name="smtp_encriptacion" class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl font-bold bg-gradient-to-r from-blue-50 to-blue-100">
                        <option value="tls" <?= ($config['smtp_encriptacion'] ?? '')=='tls'?'selected':'' ?>>TLS</option>
                        <option value="ssl" <?= ($config['smtp_encriptacion'] ?? '')=='ssl'?'selected':'' ?>>SSL</option>
                        <option value="">Ninguna</option>
                    </select>
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Usuario SMTP</label>
                    <input type="email" name="smtp_usuario" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Contraseña SMTP</label>
                    <input type="password" name="smtp_clave" value="<?= htmlspecialchars($config['smtp_clave'] ?? '') ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Correo de envío</label>
                    <input type="email" name="correo_from" value="<?= htmlspecialchars($config['correo_from'] ?? '') ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Nombre de envío</label>
                    <input type="text" name="nombre_from" value="<?= htmlspecialchars($config['nombre_from'] ?? 'Sansouci Desk') ?>" required 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Correos que reciben notificaciones (separados por coma)</label>
                    <textarea name="correos_notificacion" rows="4" required 
                              class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition resize-none">
<?= htmlspecialchars($config['correos_notificacion'] ?? '') ?>
                    </textarea>
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Correo para Prueba</label>
                    <input type="email" name="test_email" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>" placeholder="prueba@tuemail.com" 
                           class="w-full p-6 border-4 border-blue-300 rounded-2xl text-2xl focus:border-blue-900 transition">
                </div>
                <div class="md:col-span-2 text-center">
                    <div class="inline-flex items-center space-x-8 bg-gradient-to-r from-yellow-400 to-orange-400 p-8 rounded-3xl shadow-2xl">
                        <input type="checkbox" name="activado" id="activado" <?= $config['activado'] ? 'checked' : '' ?> 
                               class="w-24 h-24 rounded-full">
                        <label for="activado" class="text-5xl font-bold text-blue-900 cursor-pointer">ACTIVAR ENVÍO DE CORREOS</label>
                    </div>
                </div>
                <div class="md:col-span-2 text-center space-x-12 mt-10">
                    <button type="submit" name="guardar" 
                            class="bg-gradient-to-r from-green-600 to-green-500 text-white px-32 py-16 rounded-full text-5xl font-bold hover:from-green-700 hover:to-green-600 shadow-3xl transform hover:scale-110 transition">
                        GUARDAR CONFIGURACIÓN
                    </button>
                    <button type="submit" name="probar" 
                            class="bg-gradient-to-r from-orange-600 to-orange-500 text-white px-32 py-16 rounded-full text-5xl font-bold hover:from-orange-700 hover:to-orange-600 shadow-3xl transform hover:scale-110 transition">
                        PROBAR ENVÍO
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center mt-16">
            <a href="mantenimiento.php" class="text-blue-300 hover:text-white text-2xl underline">
                Volver al Mantenimiento
            </a>
        </div>
    </div>
</div>

<?php 
ob_end_flush();
require 'footer.php'; 
?>