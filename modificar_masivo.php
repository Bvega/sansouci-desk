<?php
ob_start();

require __DIR__ . '/config.php';
require 'header.php';

// --- Verificar usuario autenticado ---
if (!isset($user) || !is_array($user) || empty($user['email'])) {
    header('Location: login.php');
    exit();
}

// --- Obtener IDs de tickets seleccionados ---
$ids = [];

// Desde GET (cuando venimos de tickets.php)
if (isset($_GET['ids']) && $_GET['ids'] !== '') {
    $ids_raw = explode(',', $_GET['ids']);
    foreach ($ids_raw as $id) {
        $id = (int) trim($id);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
}

// Desde POST (cuando enviamos el formulario de modificación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = [];
    foreach ($_POST['ids'] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
}

$ids = array_values(array_unique($ids));

if (empty($ids)) {
    ?>
    <h1 class="text-4xl font-bold text-blue-900 mb-6">Modificación masiva</h1>
    <div class="bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-xl text-lg">
        No se recibieron tickets válidos para modificar. Vuelve a la lista y selecciona al menos uno.
    </div>
    <div class="mt-6">
        <a href="tickets.php" class="inline-block px-6 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">
            Volver a Tickets
        </a>
    </div>
    <?php
    require 'footer.php';
    ob_end_flush();
    exit();
}

// --- Cargar tickets seleccionados para mostrar resumen ---
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT t.*
    FROM tickets t
    WHERE t.id IN ($placeholders)
    ORDER BY t.creado_el DESC
");
$stmt->execute($ids);
$tickets_seleccionados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Cargar listas auxiliares (agentes y tipos de servicio) ---

// Agentes (agente / admin / superadmin)
$agentes = [];
try {
    $stmtAg = $pdo->query("
        SELECT id, nombre, rol
        FROM users
        WHERE rol IN ('agente','administrador','superadmin')
        ORDER BY nombre
    ");
    $agentes = $stmtAg->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $agentes = [];
}

// Tipos de servicio (texto, como en la columna tipo_servicio de tickets)
$tipos_servicio = [];
try {
    $stmtTs = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
    $tipos_servicio = $stmtTs->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $tipos_servicio = [];
}

// --- Intentar precargar valores comunes si todos los tickets comparten el mismo ---
$valores_actuales = [
    'estado'        => '',
    'prioridad'     => '',
    'tipo_servicio' => '',
    'agente_id'     => '',
];

if (count($tickets_seleccionados) === 1) {
    $t = $tickets_seleccionados[0];
    $valores_actuales['estado']        = $t['estado']        ?? '';
    $valores_actuales['prioridad']     = $t['prioridad']     ?? '';
    $valores_actuales['tipo_servicio'] = $t['tipo_servicio'] ?? '';
    $valores_actuales['agente_id']     = $t['agente_id']     ?? '';
} elseif (count($tickets_seleccionados) > 1) {
    $primer = $tickets_seleccionados[0];
    foreach (array_keys($valores_actuales) as $campo) {
        $todos_iguales = true;
        $valor_ref     = $primer[$campo] ?? null;
        foreach ($tickets_seleccionados as $t) {
            if (($t[$campo] ?? null) !== $valor_ref) {
                $todos_iguales = false;
                break;
            }
        }
        if ($todos_iguales) {
            $valores_actuales[$campo] = $valor_ref;
        }
    }
}

// --- Procesar formulario de actualización ---
$mensaje = '';
$tipo_mensaje = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'aplicar_cambios') {

    $estado_nuevo        = $_POST['estado']        ?? '';
    $prioridad_nueva     = $_POST['prioridad']     ?? '';
    $tipo_servicio_nuevo = $_POST['tipo_servicio'] ?? '';
    $agente_id_nuevo     = $_POST['agente_id']     ?? '';

    $set = [];
    $paramsUpdate = [];

    if ($estado_nuevo !== '') {
        $set[]          = 'estado = ?';
        $paramsUpdate[] = $estado_nuevo;
    }
    if ($prioridad_nueva !== '') {
        $set[]          = 'prioridad = ?';
        $paramsUpdate[] = $prioridad_nueva;
    }
    if ($tipo_servicio_nuevo !== '') {
        $set[]          = 'tipo_servicio = ?';
        $paramsUpdate[] = $tipo_servicio_nuevo;
    }
    if ($agente_id_nuevo !== '') {
        $set[]          = 'agente_id = ?';
        $paramsUpdate[] = (int) $agente_id_nuevo;
    }

    if (empty($set)) {
        $mensaje      = 'No seleccionaste ningún cambio para aplicar.';
        $tipo_mensaje = 'warning';
    } else {
        $set[] = 'actualizado_el = NOW()';

        $sql = 'UPDATE tickets SET ' . implode(', ', $set) . ' WHERE id IN (' . $placeholders . ')';
        $paramsUpdate = array_merge($paramsUpdate, $ids);

        try {
            $stmtUpd = $pdo->prepare($sql);
            $stmtUpd->execute($paramsUpdate);
            $afectados = $stmtUpd->rowCount();

            $mensaje      = $afectados . ' ticket(s) actualizados correctamente.';
            $tipo_mensaje = 'success';

            // Recargar tickets para ver cambios
            $stmt = $pdo->prepare("
                SELECT t.*
                FROM tickets t
                WHERE t.id IN ($placeholders)
                ORDER BY t.creado_el DESC
            ");
            $stmt->execute($ids);
            $tickets_seleccionados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {
            $mensaje      = 'Ocurrió un problema al actualizar los tickets.';
            $tipo_mensaje = 'error';
        }
    }
}
?>
<h1 class="text-4xl font-bold text-blue-900 mb-6">Modificación masiva de tickets</h1>

<?php if ($mensaje): ?>
    <?php
    $classes = [
        'success' => 'bg-green-100 border-green-400 text-green-800',
        'error'   => 'bg-red-100 border-red-400 text-red-800',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
        'info'    => 'bg-blue-100 border-blue-400 text-blue-800',
    ];
    $class = $classes[$tipo_mensaje] ?? $classes['info'];
    ?>
    <div class="mb-6 px-6 py-4 border rounded-xl text-lg <?= $class ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Formulario de cambios -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Cambios a aplicar</h2>
        <p class="text-sm text-gray-500 mb-4">
            Solo se aplicarán los campos donde selecciones un valor. Las opciones que dejes en "No cambiar" se mantendrán como están.
        </p>

        <form method="POST" class="space-y-6">
            <?php foreach ($ids as $id): ?>
                <input type="hidden" name="ids[]" value="<?= (int) $id ?>">
            <?php endforeach; ?>

            <div>
                <label class="block text-lg font-semibold mb-1">Estado</label>
                <select name="estado"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No cambiar</option>
                    <option value="abierto"     <?= $valores_actuales['estado']==='abierto'?'selected':'' ?>>Abierto</option>
                    <option value="en_proceso"  <?= $valores_actuales['estado']==='en_proceso'?'selected':'' ?>>En Proceso</option>
                    <option value="cerrado"     <?= $valores_actuales['estado']==='cerrado'?'selected':'' ?>>Cerrado</option>
                </select>
            </div>

            <div>
                <label class="block text-lg font-semibold mb-1">Prioridad</label>
                <select name="prioridad"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No cambiar</option>
                    <option value="normal"  <?= $valores_actuales['prioridad']==='normal'?'selected':'' ?>>Normal</option>
                    <option value="alta"    <?= $valores_actuales['prioridad']==='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= $valores_actuales['prioridad']==='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </div>

            <div>
                <label class="block text-lg font-semibold mb-1">Tipo de servicio</label>
                <select name="tipo_servicio"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No cambiar</option>
                    <?php foreach ($tipos_servicio as $ts): ?>
                        <option value="<?= htmlspecialchars($ts) ?>" <?= $valores_actuales['tipo_servicio']===$ts?'selected':'' ?>>
                            <?= htmlspecialchars($ts) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-lg font-semibold mb-1">Asignar a agente</label>
                <select name="agente_id"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No cambiar</option>
                    <?php foreach ($agentes as $ag): ?>
                        <option value="<?= (int) $ag['id'] ?>" <?= (string)$valores_actuales['agente_id']===(string)$ag['id']?'selected':'' ?>>
                            <?= htmlspecialchars($ag['nombre']) ?> (<?= htmlspecialchars($ag['rol']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-4 pt-4">
                <button type="submit" name="accion" value="aplicar_cambios"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl shadow">
                    Aplicar cambios a <?= count($ids) ?> ticket(s)
                </button>
                <a href="tickets.php"
                   class="inline-block px-6 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100">
                    Cancelar y volver
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen de tickets -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">
            Tickets seleccionados (<?= count($tickets_seleccionados) ?>)
        </h2>
        <div class="space-y-3 max-h-[480px] overflow-y-auto pr-2">
            <?php foreach ($tickets_seleccionados as $t): 
                $num = $t['numero'] ?? ('TCK-' . str_pad($t['id'], 5, '0', STR_PAD_LEFT));
            ?>
                <div class="border border-blue-100 rounded-xl px-4 py-3 bg-blue-50">
                    <div class="font-bold text-blue-900">
                        #<?= htmlspecialchars($num) ?> · <?= htmlspecialchars($t['asunto']) ?>
                    </div>
                    <div class="text-sm text-gray-600">
                        Cliente: <?= htmlspecialchars($t['cliente_email']) ?>
                    </div>
                    <div class="text-sm text-gray-500">
                        Estado: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$t['estado']))) ?> ·
                        Prioridad: <?= htmlspecialchars(ucfirst($t['prioridad'])) ?> ·
                        Tipo: <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
require 'footer.php';
ob_end_flush();
