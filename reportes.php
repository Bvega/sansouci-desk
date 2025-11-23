<?php
ob_start();
require 'header.php'; // aquí ya tenemos $pdo y $user

// Solo administradores y superadmins pueden ver reportes
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'])) {
    ?>
    <div class="bg-white rounded-3xl shadow-2xl p-10">
        <h1 class="text-3xl font-bold text-blue-900 mb-4">Reportes</h1>
        <p class="text-lg text-gray-600">
            Esta sección solo está disponible para usuarios con rol de
            <strong>administrador</strong> o <strong>superadmin</strong>.
        </p>
    </div>
    <?php
    ob_end_flush();
    require 'footer.php';
    exit;
}

// === Filtros de fecha ===
$hoy = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');

$fecha_desde = $_GET['desde'] ?? $primer_dia_mes;
$fecha_hasta = $_GET['hasta'] ?? $hoy;

// Normalizar formato (no hacemos validación avanzada para mantenerlo simple)
$desde_sql = $fecha_desde . ' 00:00:00';
$hasta_sql = $fecha_hasta . ' 23:59:59';

$rango_params = [$desde_sql, $hasta_sql];

// === 1. KPI básicos ===
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(estado = 'abierto') AS abiertos,
        SUM(estado = 'en_proceso') AS en_proceso,
        SUM(estado = 'cerrado') AS cerrados
    FROM tickets
    WHERE creado_el BETWEEN ? AND ?
");
$stmt->execute($rango_params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => 0,
    'abiertos' => 0,
    'en_proceso' => 0,
    'cerrados' => 0,
];

// === 2. Tickets por tipo de servicio ===
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(tipo_servicio, 'General') AS tipo_servicio,
        COUNT(*) AS cantidad
    FROM tickets
    WHERE creado_el BETWEEN ? AND ?
    GROUP BY tipo_servicio
    ORDER BY cantidad DESC
");
$stmt->execute($rango_params);
$por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// === 3. Tickets por agente ===
$stmt = $pdo->prepare("
    SELECT 
        u.nombre AS agente,
        COUNT(*) AS total
    FROM tickets t
    LEFT JOIN users u ON t.agente_id = u.id
    WHERE t.creado_el BETWEEN ? AND ?
    GROUP BY t.agente_id
    ORDER BY total DESC
");
$stmt->execute($rango_params);
$por_agente = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// === 4. Últimos tickets del rango ===
$stmt = $pdo->prepare("
    SELECT 
        t.*, 
        u.nombre AS agente_nombre
    FROM tickets t
    LEFT JOIN users u ON t.agente_id = u.id
    WHERE t.creado_el BETWEEN ? AND ?
    ORDER BY t.creado_el DESC
    LIMIT 15
");
$stmt->execute($rango_params);
$ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="flex flex-col gap-8">

    <!-- Título + filtros -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-4xl font-extrabold text-blue-900 mb-2">Reportes de Tickets</h1>
            <p class="text-lg text-gray-600">
                Resumen de tickets entre 
                <strong><?= htmlspecialchars($fecha_desde) ?></strong> y 
                <strong><?= htmlspecialchars($fecha_hasta) ?></strong>.
            </p>
        </div>

        <form method="GET" class="bg-white shadow-lg rounded-2xl px-6 py-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Desde</label>
                <input type="date" name="desde"
                       value="<?= htmlspecialchars($fecha_desde) ?>"
                       class="border-2 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Hasta</label>
                <input type="date" name="hasta"
                       value="<?= htmlspecialchars($fecha_hasta) ?>"
                       class="border-2 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="pb-1">
                <button type="submit"
                        class="bg-blue-700 hover:bg-blue-800 text-white font-bold px-6 py-2 rounded-xl text-sm shadow-lg">
                    Aplicar filtro
                </button>
            </div>
        </form>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-blue-700">
            <p class="text-sm text-gray-500">Total de tickets</p>
            <p class="text-3xl font-extrabold text-blue-900 mt-2"><?= (int)$kpi['total'] ?></p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-green-600">
            <p class="text-sm text-gray-500">Abiertos</p>
            <p class="text-3xl font-extrabold text-green-700 mt-2"><?= (int)$kpi['abiertos'] ?></p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-yellow-500">
            <p class="text-sm text-gray-500">En proceso</p>
            <p class="text-3xl font-extrabold text-yellow-600 mt-2"><?= (int)$kpi['en_proceso'] ?></p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-gray-500">
            <p class="text-sm text-gray-500">Cerrados</p>
            <p class="text-3xl font-extrabold text-gray-700 mt-2"><?= (int)$kpi['cerrados'] ?></p>
        </div>
    </div>

    <!-- Tablas: por tipo de servicio y por agente -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Por tipo de servicio -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Tickets por tipo de servicio</h2>

            <?php if (empty($por_tipo)): ?>
                <p class="text-gray-500">No hay tickets en el rango seleccionado.</p>
            <?php else: ?>
                <table class="w-full table-auto text-sm">
                    <thead class="bg-blue-900 text-white">
                        <tr>
                            <th class="px-4 py-2 text-left">Tipo de servicio</th>
                            <th class="px-4 py-2 text-right">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($por_tipo as $fila): ?>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($fila['tipo_servicio']) ?>
                            </td>
                            <td class="px-4 py-2 text-right font-bold">
                                <?= (int)$fila['cantidad'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Por agente -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Tickets por agente</h2>

            <?php if (empty($por_agente)): ?>
                <p class="text-gray-500">No hay tickets en el rango seleccionado.</p>
            <?php else: ?>
                <table class="w-full table-auto text-sm">
                    <thead class="bg-blue-900 text-white">
                        <tr>
                            <th class="px-4 py-2 text-left">Agente</th>
                            <th class="px-4 py-2 text-right">Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($por_agente as $fila): ?>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($fila['agente'] ?? 'Sin asignar') ?>
                            </td>
                            <td class="px-4 py-2 text-right font-bold">
                                <?= (int)$fila['total'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Últimos tickets -->
    <div class="bg-white rounded-2xl shadow-2xl p-6">
        <h2 class="text-2xl font-bold text-blue-900 mb-4">Últimos tickets del rango</h2>

        <?php if (empty($ultimos)): ?>
            <p class="text-gray-500">No hay tickets en el rango seleccionado.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Fecha</th>
                            <th class="px-4 py-2 text-left">Cliente</th>
                            <th class="px-4 py-2 text-left">Asunto</th>
                            <th class="px-4 py-2 text-left">Tipo servicio</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-left">Agente</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ultimos as $t): 
                        $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'], 5, '0', STR_PAD_LEFT);
                    ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2 font-semibold">
                                <?= htmlspecialchars($numero) ?>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['creado_el']))) ?>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($t['cliente_email']) ?>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($t['asunto']) ?>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?>
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-3 py-1 rounded-full text-xs font-bold
                                    <?= $t['estado'] === 'abierto' ? 'bg-green-100 text-green-800' :
                                       ($t['estado'] === 'cerrado' ? 'bg-gray-400 text-white' :
                                                                    'bg-yellow-100 text-yellow-800') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $t['estado'])) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
ob_end_flush();
require 'footer.php';
?>
