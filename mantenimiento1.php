<?php require 'header.php'; 
if(!in_array($user['rol'],['administrador','superadmin'])) { 
    die('<div class="p-6 text-center text-red-600 text-lg">Acceso denegado. Solo administradores.</div>'); 
}

// === GUARDAR CONFIG EMAIL ===
if(isset($_POST['guardar_email'])){
    $datos = [
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_user' => $_POST['smtp_user'],
        'smtp_pass' => $_POST['smtp_pass'],
        'smtp_from_email' => $_POST['smtp_from_email'],
        'smtp_from_name' => $_POST['smtp_from_name'],
        'notificar_usuarios' => isset($_POST['notificar_usuarios']) ? 1 : 0
    ];
    $pdo->prepare("INSERT INTO config_email (smtp_host,smtp_port,smtp_user,smtp_pass,smtp_from_email,smtp_from_name,notificar_usuarios) 
                   VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE 
                   smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port), smtp_user=VALUES(smtp_user), 
                   smtp_pass=VALUES(smtp_pass), smtp_from_email=VALUES(smtp_from_email), 
                   smtp_from_name=VALUES(smtp_from_name), notificar_usuarios=VALUES(notificar_usuarios)")
        ->execute(array_values($datos));
    $msg = "Configuración de correo guardada";
}

// CARGAR CONFIG EMAIL
$stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
$config_email = $stmt->fetch() ?: [
    'smtp_host'=>'smtp.gmail.com','smtp_port'=>587,'smtp_from_email'=>'soporte@sansouci.com.do','smtp_from_name'=>'Sansouci Desk','notificar_usuarios'=>1
];

// === TU CÓDIGO EXISTENTE DE USUARIOS Y PERMISOS (PEGADO AQUÍ) ===
if(isset($_POST['crear'])){
    $email = trim($_POST['email']);
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if($check->fetch()){
        $msg = "<span class='text-red-600 font-bold'>ERROR: Email ya registrado</span>";
    } else {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nombre, email, password, rol) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['nombre'], $email, $hash, $_POST['rol']]);
        $user_id = $pdo->lastInsertId();
        
        $permisos = [
            'agente' => ['dashboard','tickets'],
            'administrador' => ['dashboard','tickets','reportes','mantenimiento'],
            'superadmin' => ['dashboard','tickets','reportes','mantenimiento']
        ];
        foreach($permisos[$_POST['rol']] as $m){
            $pdo->prepare("INSERT IGNORE INTO permisos_modulos (user_id, modulo, permitido) VALUES (?,?,1)")->execute([$user_id, $m]);
        }
        $msg = "Usuario creado";
    }
}

if(isset($_POST['editar_usuario'])){
    $id = $_POST['editar_id'];
    $nuevo_email = trim($_POST['email']);
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$nuevo_email, $id]);
    if($check->fetch()){
        $msg = "<span class='text-red-600 font-bold'>ERROR: Email en uso</span>";
    } else {
        $sql = "UPDATE users SET nombre=?, email=?, rol=?";
        $params = [$_POST['nombre'], $nuevo_email, $_POST['rol']];
        if(!empty($_POST['password'])){
            $sql .= ", password=?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);

        $pdo->prepare("DELETE FROM permisos_modulos WHERE user_id = ?")->execute([$id]);
        $permisos = [
            'agente' => ['dashboard','tickets'],
            'administrador' => ['dashboard','tickets','reportes','mantenimiento'],
            'superadmin' => ['dashboard','tickets','reportes','mantenimiento']
        ];
        foreach($permisos[$_POST['rol']] as $m){
            $pdo->prepare("INSERT INTO permisos_modulos (user_id, modulo, permitido) VALUES (?,?,1)")->execute([$id, $m]);
        }
        $msg = "Usuario actualizado";
    }
}

if(isset($_POST['guardar_rol_permisos'])){
    $rol = $_POST['rol_seleccionado'];
    $modulos_seleccionados = $_POST['modulo'] ?? [];
    
    $usuarios_rol = $pdo->prepare("SELECT id FROM users WHERE rol = ?");
    $usuarios_rol->execute([$rol]);
    $usuarios = $usuarios_rol->fetchAll(PDO::FETCH_COLUMN);
    
    if(empty($usuarios)){
        $msg = "No hay usuarios con rol '$rol'";
    } else {
        foreach($usuarios as $user_id){
            $pdo->prepare("DELETE FROM permisos_modulos WHERE user_id = ?")->execute([$user_id]);
            foreach($modulos_seleccionados as $modulo){
                $pdo->prepare("INSERT INTO permisos_modulos (user_id, modulo, permitido) VALUES (?,?,1)")->execute([$user_id, $modulo]);
            }
            $todos_modulos = ['dashboard','tickets','reportes','mantenimiento'];
            $no_seleccionados = array_diff($todos_modulos, $modulos_seleccionados);
            foreach($no_seleccionados as $modulo){
                $pdo->prepare("INSERT INTO permisos_modulos (user_id, modulo, permitido) VALUES (?,?,0)")->execute([$user_id, $modulo]);
            }
        }
        $msg = "Permisos del rol '$rol' actualizados para " . count($usuarios) . " usuarios";
    }
}

$usuarios = $pdo->query("SELECT u.*, GROUP_CONCAT(p.modulo SEPARATOR ',') as modulos_permitidos 
                         FROM users u 
                         LEFT JOIN permisos_modulos p ON u.id=p.user_id AND p.permitido=1 
                         GROUP BY u.id")->fetchAll();

$permisos_por_rol = [];
foreach(['agente','administrador','superadmin'] as $r){
    $stmt = $pdo->prepare("SELECT DISTINCT modulo FROM permisos_modulos p 
                           JOIN users u ON p.user_id=u.id 
                           WHERE u.rol=? AND p.permitido=1");
    $stmt->execute([$r]);
    $permisos_por_rol[$r] = array_column($stmt->fetchAll(), 'modulo');
}

$rol_actual = $_POST['rol_seleccionado'] ?? 'agente';
?>
<div class="max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold text-blue-900 mb-8 text-center">
        <i class="fas fa-tools mr-3"></i> MANTENIMIENTO
    </h1>

    <?php if(isset($msg)): ?>
    <div class="bg-green-50 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-6 text-center font-semibold">
        <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- PESTAÑAS -->
    <div class="bg-white rounded-xl shadow-xl overflow-hidden">
        <div class="flex border-b border-gray-200">
            <button onclick="mostrarTab('usuarios')" id="tab_usuarios" class="flex-1 py-4 px-6 text-lg font-bold text-blue-900 bg-blue-50 border-r border-gray-200 hover:bg-blue-100 transition">Usuarios</button>
            <button onclick="mostrarTab('roles')" id="tab_roles" class="flex-1 py-4 px-6 text-lg font-bold text-gray-700 hover:bg-gray-100 transition">Permisos por Rol</button>
            <button onclick="mostrarTab('email')" id="tab_email" class="flex-1 py-4 px-6 text-lg font-bold text-gray-700 hover:bg-gray-100 transition">Config. Email</button>
        </div>

        <div class="p-8">
            <!-- USUARIOS -->
            <div id="contenido_usuarios">
                <!-- TU CÓDIGO COMPLETO DE USUARIOS AQUÍ -->
                <div class="bg-gray-50 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold text-blue-900 mb-4">Crear Usuario</h2>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="text" name="nombre" placeholder="Nombre" required class="p-3 border rounded-lg text-base">
                        <input type="email" name="email" placeholder="email@sansouci.com.do" required class="p-3 border rounded-lg text-base">
                        <input type="password" name="password" placeholder="Contraseña" required class="p-3 border rounded-lg text-base">
                        <select name="rol" class="p-3 border rounded-lg text-base font-medium bg-yellow-50">
                            <option value="agente">Agente</option>
                            <option value="administrador">Administrador</option>
                            <?php if($user['rol']=='superadmin'): ?>
                            <option value="superadmin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                        <div class="md:col-span-2 text-center">
                            <button name="crear" class="bg-green-600 text-white px-12 py-3 rounded-lg font-bold hover:bg-green-700 transition">
                                Crear Usuario
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto text-sm">
                        <thead class="bg-blue-900 text-white">
                            <tr>
                                <th class="p-3 text-left">Usuario</th>
                                <th class="p-3 text-left">Email</th>
                                <th class="p-3 text-left">Rol</th>
                                <th class="p-3 text-left">Módulos</th>
                                <th class="p-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($usuarios as $u): 
                                $mods = $u['modulos_permitidos'] ? explode(',', $u['modulos_permitidos']) : [];
                                $nombre_js = addslashes(htmlspecialchars($u['nombre']));
                                $email_js = addslashes(htmlspecialchars($u['email']));
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-medium"><?= htmlspecialchars($u['nombre']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="p-3">
                                    <span class="px-4 py-2 rounded-full text-xs font-bold <?= $u['rol']=='superadmin'?'bg-red-600':($u['rol']=='administrador'?'bg-orange-600':'bg-blue-700') ?> text-white">
                                        <?= strtoupper($u['rol']) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php foreach(['dashboard','tickets','reportes','mantenimiento'] as $m): 
                                        $has = in_array($m, $mods);
                                    ?>
                                    <span class="inline-block mr-2 mb-1 px-3 py-1 rounded text-xs font-medium <?= $has?'bg-green-100 text-green-800':'bg-gray-200 text-gray-600' ?>">
                                        <?= strtoupper($m) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="p-3 text-center space-x-2">
                                    <button onclick="abrirModal(<?= $u['id'] ?>, '<?= $nombre_js ?>', '<?= $email_js ?>', '<?= $u['rol'] ?>')" 
                                            class="bg-yellow-500 text-white px-4 py-2 rounded text-sm font-bold hover:bg-yellow-600">
                                        Editar
                                    </button>
                                    <?php if($user['rol']=='superadmin' && $u['id'] != $user['id']): ?>
                                    <a href="eliminar_usuario.php?id=<?= $u['id'] ?>" 
                                       onclick="return confirm('¿Eliminar permanentemente?')" 
                                       class="bg-red-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-red-700">
                                        Eliminar
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PERMISOS POR ROL -->
            <div id="contenido_roles" class="hidden">
                <!-- TU CÓDIGO DE PERMISOS POR ROL AQUÍ -->
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 rounded-xl p-8 text-white">
                    <h2 class="text-2xl font-bold mb-6 text-center">Configurar Permisos por Rol</h2>
                    <form method="POST" class="space-y-6">
                        <div class="text-center">
                            <select name="rol_seleccionado" class="p-4 border-4 border-yellow-400 rounded-xl text-lg font-bold bg-white text-blue-900 w-full max-w-xs">
                                <option value="agente" <?= $rol_actual=='agente'?'selected':'' ?>>Agente</option>
                                <option value="administrador" <?= $rol_actual=='administrador'?'selected':'' ?>>Administrador</option>
                                <?php if($user['rol']=='superadmin'): ?>
                                <option value="superadmin" <?= $rol_actual=='superadmin'?'selected':'' ?>>Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <?php foreach(['dashboard','tickets','reportes','mantenimiento'] as $m): 
                                $checked = in_array($m, $permisos_por_rol[$rol_actual]) ? 'checked' : '';
                            ?>
                            <label class="flex items-center space-x-3 bg-white bg-opacity-20 p-4 rounded-xl hover:bg-opacity-30 transition cursor-pointer">
                                <input type="checkbox" name="modulo[]" value="<?= $m ?>" <?= $checked ?> class="w-6 h-6 text-yellow-400">
                                <span class="font-bold text-lg"><?= strtoupper($m) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center">
                            <button type="submit" name="guardar_rol_permisos" 
                                    class="bg-yellow-400 text-blue-900 px-16 py-5 rounded-xl text-xl font-bold hover:bg-yellow-300 shadow-xl transition">
                                Guardar Permisos
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                        <!-- CONFIGURACIÓN DE EMAIL - 100% FUNCIONAL -->
            <div id="contenido_email" class="hidden">
                <?php
                // PROCESAR PRUEBA DE CORREO
                if(isset($_POST['probar_email'])){
                    require 'phpmailer/src/Exception.php';
                    require 'phpmailer/src/PHPMailer.php';
                    require 'phpmailer/src/SMTP.php';
                    
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = $_POST['smtp_host'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $_POST['smtp_user'];
                        $mail->Password   = $_POST['smtp_pass'];
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = $_POST['smtp_port'];
                        $mail->CharSet    = 'UTF-8';

                        $mail->setFrom($_POST['smtp_from_email'], $_POST['smtp_from_name']);
                        $mail->addAddress($_POST['smtp_user']);
                        $mail->isHTML(true);
                        $mail->Subject = "PRUEBA DE ENVÍO - Sansouci Desk";
                        $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                            <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                            <h1 style='color: #003087; text-align: center; font-size: 32px;'>PRUEBA EXITOSA!</h1>
                            <p style='text-align: center; font-size: 20px; color: #333;'>
                                El envío de correo desde Sansouci Desk está funcionando correctamente.
                            </p>
                            <div style='text-align: center; margin: 40px 0;'>
                                <p style='background: #f0f8ff; padding: 20px; border-radius: 10px; font-size: 18px;'>
                                    Fecha y hora: " . date('d/m/Y H:i:s') . "
                                </p>
                            </div>
                            <p style='text-align: center; color: #666; font-size: 16px;'>
                                Sansouci Puerto de Santo Domingo<br>
                                soporte@sansouci.com.do
                            </p>
                        </div>";

                        $mail->send();
                        $prueba_msg = "<span class='text-green-600 font-bold'>PRUEBA ENVIADA! Revisa tu bandeja.</span>";
                    } catch (Exception $e) {
                        $prueba_msg = "<span class='text-red-600 font-bold'>ERROR: " . htmlspecialchars($mail->ErrorInfo) . "</span>";
                    }
                }
                ?>
                <div class="bg-gradient-to-r from-blue-900 to-cyan-700 rounded-2xl shadow-2xl p-10 text-white">
                    <h2 class="text-3xl font-bold mb-8 text-center">
                        <i class="fas fa-envelope mr-4"></i> CONFIGURACIÓN DE ENVÍO DE CORREO
                    </h2>

                    <?php if(isset($prueba_msg)): ?>
                    <div class="bg-white bg-opacity-20 border-4 border-yellow-400 text-yellow-100 px-8 py-6 rounded-2xl mb-10 text-xl font-bold text-center">
                        <?= $prueba_msg ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-lg font-bold mb-3">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?= htmlspecialchars($config_email['smtp_host']) ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div>
                            <label class="block text-lg font-bold mb-3">Puerto</label>
                            <input type="number" name="smtp_port" value="<?= $config_email['smtp_port'] ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div>
                            <label class="block text-lg font-bold mb-3">Usuario (email)</label>
                            <input type="email" name="smtp_user" value="<?= htmlspecialchars($config_email['smtp_user'] ?? '') ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div>
                            <label class="block text-lg font-bold mb-3">Contraseña / App Password</label>
                            <input type="password" name="smtp_pass" value="<?= htmlspecialchars($config_email['smtp_pass'] ?? '') ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div>
                            <label class="block text-lg font-bold mb-3">From Email</label>
                            <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($config_email['smtp_from_email']) ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div>
                            <label class="block text-lg font-bold mb-3">From Name</label>
                            <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($config_email['smtp_from_name']) ?>" required 
                                   class="w-full p-4 rounded-lg text-black text-lg focus:ring-4 focus:ring-yellow-400 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center space-x-4 cursor-pointer">
                                <input type="checkbox" name="notificar_usuarios" <?= $config_email['notificar_usuarios']?'checked':'' ?> 
                                       class="w-8 h-8 text-yellow-400 rounded focus:ring-yellow-300">
                                <span class="text-xl font-bold">Notificar a todos los agentes al crear ticket</span>
                            </label>
                        </div>
                        <div class="md:col-span-2 text-center space-x-8">
                            <button type="submit" name="probar_email" 
                                    class="bg-orange-500 text-white px-20 py-8 rounded-full text-2xl font-bold hover:bg-orange-600 shadow-2xl transform hover:scale-105 transition">
                                <i class="fas fa-paper-plane mr-6"></i> PROBAR ENVÍO
                            </button>
                            <button type="submit" name="guardar_email" 
                                    class="bg-yellow-400 text-blue-900 px-32 py-12 rounded-full text-4xl font-bold hover:bg-yellow-300 shadow-3xl transform hover:scale-110 transition">
                                GUARDAR CONFIGURACIÓN
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-80 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-2xl w-full">
        <h3 class="text-2xl font-bold text-blue-900 mb-6 text-center">Editar Usuario</h3>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="editar_id" id="editar_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="text" name="nombre" id="editar_nombre" required placeholder="Nombre" class="p-4 border rounded-lg">
                <input type="email" name="email" id="editar_email" required placeholder="Email" class="p-4 border rounded-lg">
                <input type="password" name="password" placeholder="Nueva contraseña (opcional)" class="p-4 border rounded-lg">
                <select name="rol" id="editar_rol" class="p-4 border rounded-lg font-medium bg-yellow-50">
                    <option value="agente">Agente</option>
                    <option value="administrador">Administrador</option>
                    <?php if($user['rol']=='superadmin'): ?>
                    <option value="superadmin">Super Admin</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="text-center space-x-4">
                <button type="button" onclick="cerrarModal()" class="bg-gray-500 text-white px-10 py-3 rounded-lg font-bold hover:bg-gray-600">
                    Cancelar
                </button>
                <button type="submit" name="editar_usuario" class="bg-green-600 text-white px-12 py-3 rounded-lg font-bold hover:bg-green-700">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarTab(tab) {
    document.querySelectorAll('[id^="contenido_"]').forEach(el => el.classList.add('hidden'));
    document.getElementById('contenido_'+tab).classList.remove('hidden');
    
    document.querySelectorAll('[id^="tab_"]').forEach(el => {
        el.classList.remove('bg-blue-50', 'text-blue-900');
        el.classList.add('text-gray-700', 'hover:bg-gray-100');
    });
    document.getElementById('tab_'+tab).classList.add('bg-blue-50', 'text-blue-900');
    document.getElementById('tab_'+tab).classList.remove('text-gray-700', 'hover:bg-gray-100');
}

function abrirModal(id, nombre, email, rol) {
    document.getElementById('editar_id').value = id;
    document.getElementById('editar_nombre').value = nombre;
    document.getElementById('editar_email').value = email;
    document.getElementById('editar_rol').value = rol;
    document.getElementById('modal').classList.remove('hidden');
}
function cerrarModal() { 
    document.getElementById('modal').classList.add('hidden'); 
}

// MOSTRAR USUARIOS POR DEFECTO
mostrarTab('usuarios');
</script>

<?php require 'footer.php'; ?>