<?php 
ob_start(); // SOLUCIONA EL ERROR DE HEADERS
require 'header.php'; 

// === PROCESAR ACCIONES MASIVAS ANTES DE CUALQUIER SALIDA HTML ===
$mensaje = '';
if($_POST && isset($_POST['seleccionar'])){
    $seleccionados = $_POST['seleccionar'];
    $accion = $_POST['accion'] ?? '';

    if(empty($seleccionados)){
        $mensaje = "Selecciona al menos un ticket";
    } else {
        if($accion == 'eliminar' && $user['rol'] == 'superadmin'){
            $in = str_repeat('?,', count($seleccionados) - 1) . '?';
            $pdo->prepare("DELETE FROM respuestas WHERE ticket_id IN ($in)")->execute($seleccionados);
            $pdo->prepare("DELETE FROM tickets WHERE id IN ($in)")->execute($seleccionados);
            $mensaje = count($seleccionados) . " ticket(s) eliminados";
        }
        if($accion == 'modificar'){
            $ids = implode(',', array_map('intval', $seleccionados));
            header("Location: modificar_masivo.php?ids=$ids");
            exit();
        }
    }
}

// === FILTROS Y CONSULTA DE TICKETS ===
$where = "WHERE 1=1";
$params = [];

if($user['rol'] == 'agente'){
    $where .= " AND t.agente_id = ?";
    $params[] = $user['id'];
}
if(isset($_GET['estado']) && $_GET['estado'] !== ''){
    $where .= " AND t.estado = ?";
    $params[] = $_GET['estado'];
}

$stmt = $pdo->prepare("SELECT t.*, u.nombre as agente_nombre 
                       FROM tickets t 
                       LEFT JOIN users u ON t.agente_id = u.id 
                       $where 
                       ORDER BY t.creado_el DESC");
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<h1 class="text-4xl font-bold text-blue-900 mb-10">Gestión de Tickets (<?= count($tickets) ?>)</h1>

<?php if($mensaje): ?>
<div class="bg-yellow-100 border-4 border-yellow-600 text-yellow-800 px-8 py-6 rounded-2xl mb-10 text-2xl font-bold">
    <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<!-- FILTROS + BOTONES MASIVOS -->
<div class="bg-white rounded-2xl shadow-xl p-8 mb-10 flex flex-wrap items-center justify-between gap-6">
    <div class="flex flex-wrap gap-6">
        <a href="tickets.php" class="px-8 py-4 bg-gray-200 rounded-xl font-bold text-xl <?= !isset($_GET['estado']) ? 'bg-blue-900 text-white' : '' ?>">Todos</a>
        <a href="tickets.php?estado=abierto" class="px-8 py-4 bg-green-200 rounded-xl font-bold text-xl <?= ($_GET['estado'] ?? '')=='abierto' ? 'bg-green-700 text-white' : '' ?>">Abiertos</a>
        <a href="tickets.php?estado=en_proceso" class="px-8 py-4 bg-yellow-200 rounded-xl font-bold text-xl <?= ($_GET['estado'] ?? '')=='en_proceso' ? 'bg-yellow-700 text-white' : '' ?>">En Proceso</a>
        <a href="tickets.php?estado=cerrado" class="px-8 py-4 bg-gray-500 text-white rounded-xl font-bold text-xl <?= ($_GET['estado'] ?? '')=='cerrado' ? 'bg-gray-700' : '' ?>">Cerrados</a>
    </div>

    <div class="flex gap-4">
        <button type="submit" form="form-tickets" name="accion" value="modificar"
                class="bg-yellow-600 text-white px-10 py-5 rounded-xl font-bold text-xl hover:bg-yellow-700 shadow-lg">
            MODIFICAR
        </button>
        <?php if($user['rol'] == 'superadmin'): ?>
        <button type="submit" form="form-tickets" name="accion" value="eliminar"
                onclick="return confirm('¿ELIMINAR permanentemente los tickets seleccionados?')"
                class="bg-red-600 text-white px-10 py-5 rounded-xl font-bold text-xl hover:bg-red-700 shadow-lg">
            ELIMINAR
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- TABLA CON CHECKBOX -->
<form method="POST" id="form-tickets">
<div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full table-auto">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="p-6 text-center w-16">
                        <input type="checkbox" id="select_all" class="w-6 h-6 rounded border-gray-300">
                    </th>
                    <th class="p-6 text-left">Ticket</th>
                    <th class="p-6 text-left">Cliente</th>
                    <th class="p-6 text-left">Asunto</th>
                    <th class="p-6 text-left">Tipo Servicio</th>
                    <th class="p-6 text-left">Estado</th>
                    <th class="p-6 text-left">Prioridad</th>
                    <th class="p-6 text-left">Agente</th>
                    <th class="p-6 text-left">Fecha</th>
                    <th class="p-6 text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tickets)): ?>
                <tr>
                    <td colspan="10" class="p-20 text-center text-3xl text-gray-500">
                        No hay tickets
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($tickets as $t): 
                    $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
                ?>
                <tr class="border-b-4 border-blue-100 hover:bg-blue-50 transition text-lg">
                    <td class="p-6 text-center">
                        <input type="checkbox" name="seleccionar[]" value="<?= $t['id'] ?>" class="w-6 h-6 checkbox-item rounded border-gray-300">
                    </td>
                    <td class="p-6 font-bold text-2xl">#<?= htmlspecialchars($numero) ?></td>
                    <td class="p-6"><?= htmlspecialchars($t['cliente_email']) ?></td>
                    <td class="p-6 font-semibold"><?= htmlspecialchars($t['asunto']) ?></td>
                    <td class="p-6">
                        <span class="px-6 py-3 bg-purple-200 text-purple-800 rounded-full font-bold">
                            <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?>
                        </span>
                    </td>
                    <td class="p-6">
                        <span class="px-6 py-3 rounded-full font-bold <?= $t['estado']=='abierto'?'bg-green-200 text-green-800':($t['estado']=='cerrado'?'bg-gray-400 text-white':'bg-yellow-200 text-yellow-800') ?>">
                            <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                        </span>
                    </td>
                    <td class="p-6">
                        <span class="px-6 py-3 rounded-full font-bold <?= $t['prioridad']=='urgente'?'bg-red-200 text-red-800':($t['prioridad']=='alta'?'bg-orange-200 text-orange-800':'bg-blue-200 text-blue-800') ?>">
                            <?= ucfirst($t['prioridad']) ?>
                        </span>
                    </td>
                    <td class="p-6"><?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?></td>
                    <td class="p-6"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></td>
                    <td class="p-6 text-center">
                        <a href="responder.php?ticket_id=<?= $t['id'] ?>" 
                           class="bg-green-600 text-white px-10 py-4 rounded-xl font-bold hover:bg-green-700 shadow-lg inline-block">
                            RESPONDER
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</form>

<script>
document.getElementById('select_all').addEventListener('change', function() {
    document.querySelectorAll('.checkbox-item').forEach(cb => cb.checked = this.checked);
});
</script>

<?php 
ob_end_flush(); // CIERRA EL BUFFER
require 'footer.php'; 
?>