<?php require 'header.php'; 
if(!in_array($user['rol'],['administrador','superadmin'])) die("Acceso denegado");

// CARGAR CONFIG
$stmt = $pdo->query("SELECT * FROM config_asignacion LIMIT 1");
$config = $stmt->fetch() ?: ['modo' => 'carga_trabajo', 'activa' => 1];

// GUARDAR CONFIG
if(isset($_POST['guardar_config'])){
    $modo = $_POST['modo'];
    $activa = isset($_POST['activa']) ? 1 : 0;
    $pdo->prepare("INSERT INTO config_asignacion (modo, activa) VALUES (?,?) ON DUPLICATE KEY UPDATE modo=?, activa=?")
        ->execute([$modo, $activa, $modo, $activa]);
    $config = ['modo' => $modo, 'activa' => $activa];
    $msg = "Configuración guardada";
}

// ASIGNAR CLIENTE A AGENTE
if(isset($_POST['asignar_cliente'])){
    $email = trim($_POST['cliente_email']);
    $agente_id = $_POST['agente_id'];
    if(!empty($email) && $agente_id > 0){
        $pdo->prepare("INSERT INTO asignacion_clientes (cliente_email, agente_id) VALUES (?,?) 
                       ON DUPLICATE KEY UPDATE agente_id=?")
            ->execute([$email, $agente_id, $agente_id]);
        $msg = "Cliente asignado";
    }
}

// ELIMINAR ASIGNACIÓN
if(isset($_GET['eliminar'])){
    $id = intval($_GET['eliminar']);
    $pdo->prepare("DELETE FROM asignacion_clientes WHERE id = ?")->execute([$id]);
    $msg = "Asignación eliminada";
}

$agentes = $pdo->query("SELECT id, nombre FROM users WHERE rol = 'agente' ORDER BY nombre")->fetchAll();
$asignaciones = $pdo->query("SELECT ac.*, u.nombre as agente_nombre 
                             FROM asignacion_clientes ac 
                             LEFT JOIN users u ON ac.agente_id = u.id 
                             ORDER BY ac.cliente_email")->fetchAll();
?>
<h1 class="text-3xl font-bold text-blue-900 mb-8 text-center">
    <i class="fas fa-user-cog mr-4"></i> ASIGNACIÓN AUTOMÁTICA DE TICKETS
</h1>

<?php if(isset($msg)): ?>
<div class="bg-green-50 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-8 text-center font-bold">
    <?= $msg ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
    <!-- CONFIG GLOBAL -->
    <div class="bg-white rounded-xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-blue-900 mb-6">Configuración Global</h2>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-lg font-bold mb-3">Modo de Asignación Automática</label>
                <select name="modo" class="w-full p-4 border-2 border-blue-300 rounded-lg text-lg">
                    <option value="carga_trabajo" <?= $config['modo']=='carga_trabajo'?'selected':'' ?>>
                        Carga de Trabajo (agente con menos tickets abiertos)
                    </option>
                    <option value="round_robin" <?= $config['modo']=='round_robin'?'selected':'' ?>>
                        Round Robin (turno rotativo)
                    </option>
                </select>
            </div>
            <div class="flex items-center space-x-4">
                <input type="checkbox" name="activa" id="activa" <?= $config['activa']?'checked':'' ?> class="w-6 h-6">
                <label for="activa" class="text-lg font-bold">Asignación Automática Activada</label>
            </div>
            <button name="guardar_config" class="bg-blue-900 text-white px-12 py-4 rounded-lg font-bold hover:bg-blue-800">
                Guardar Configuración
            </button>
        </form>
    </div>

    <!-- ASIGNAR CLIENTE ESPECÍFICO -->
    <div class="bg-white rounded-xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-blue-900 mb-6">Asignar Cliente a Agente Fijo</h2>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-lg font-bold mb-3">Correo del Cliente</label>
                <input type="email" name="cliente_email" required placeholder="cliente@empresa.com" 
                       class="w-full p-4 border-2 border-blue-300 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-lg font-bold mb-3">Asignar a Agente</label>
                <select name="agente_id" required class="w-full p-4 border-2 border-blue-300 rounded-lg text-lg">
                    <option value="">Seleccionar agente</option>
                    <?php foreach($agentes as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button name="asignar_cliente" class="bg-green-600 text-white px-12 py-4 rounded-lg font-bold hover:bg-green-700">
                Asignar Cliente
            </button>
        </form>

        <!-- LISTA DE ASIGNACIONES -->
        <?php if(!empty($asignaciones)): ?>
        <div class="mt-8">
            <h3 class="text-xl font-bold text-blue-900 mb-4">Asignaciones Actuales</h3>
            <div class="space-y-3">
                <?php foreach($asignaciones as $a): ?>
                <div class="bg-gray-50 rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <strong><?= htmlspecialchars($a['cliente_email']) ?></strong> → 
                        <span class="text-blue-700 font-bold"><?= htmlspecialchars($a['agente_nombre']) ?></span>
                    </div>
                    <a href="?eliminar=<?= $a['id'] ?>" onclick="return confirm('¿Eliminar esta asignación?')" 
                       class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>