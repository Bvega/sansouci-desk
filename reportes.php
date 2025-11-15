<?php require 'header.php'; 

// Filtros de fecha
$fecha_desde = $_GET['desde'] ?? date('Y-m-01'); // Primer día del mes
$fecha_hasta = $_GET['hasta'] ?? date('Y-m-d');

// Consulta con filtros
$stmt = $pdo->prepare("SELECT t.*, u.nombre as agente_nombre 
                       FROM tickets t 
                       LEFT JOIN users u ON t.agente_id = u.id 
                       WHERE DATE(t.creado_el) BETWEEN ? AND ? 
                       ORDER BY t.creado_el DESC");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DESCARGA EN EXCEL
if(isset($_GET['export']) && $_GET['export'] == 'excel'){
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Reporte_Tickets_Sansouci_' . date('d-m-Y') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<table border='1'>
    <tr style='background:#003087;color:white;font-weight:bold;'>
    <th>ID</th><th>Numero</th><th>Cliente</th><th>Asunto</th><th>Estado</th><th>Prioridad</th><th>Agente</th><th>Fecha</th>
    </tr>";
    foreach($tickets as $t){
        $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
        echo "<tr>
        <td>{$t['id']}</td>
        <td>$numero</td>
        <td>" . htmlspecialchars($t['cliente_email']) . "</td>
        <td>" . htmlspecialchars($t['asunto']) . "</td>
        <td>" . ucfirst(str_replace('_',' ',$t['estado'])) . "</td>
        <td>" . ucfirst($t['prioridad']) . "</td>
        <td>" . htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') . "</td>
        <td>" . date('d/m/Y H:i', strtotime($t['creado_el'])) . "</td>
        </tr>";
    }
    echo "</table>";
    exit();
}

// Estadísticas
$total = count($tickets);
$abiertos = count(array_filter($tickets, fn($t) => $t['estado'] == 'abierto'));
$en_proceso = count(array_filter($tickets, fn($t) => $t['estado'] == 'en_proceso'));
$cerrados = count(array_filter($tickets, fn($t) => $t['estado'] == 'cerrado'));
$urgentes = count(array_filter($tickets, fn($t) => $t['prioridad'] == 'urgente'));
?>
<h1 class="text-4xl font-bold text-blue-900 mb-10">Reportes de Requerimientos</h1>

<!-- Filtros de fecha + Botón Excel -->
<div class="bg-white rounded-2xl shadow-xl p-8 mb-10">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
        <div>
            <label class="block text-xl font-bold text-blue-900 mb-2">Desde</label>
            <input type="date" name="desde" value="<?= $fecha_desde ?>" required class="w-full p-4 border-2 border-blue-300 rounded-lg text-lg">
        </div>
        <div>
            <label class="block text-xl font-bold text-blue-900 mb-2">Hasta</label>
            <input type="date" name="hasta" value="<?= $fecha_hasta ?>" required class="w-full p-4 border-2 border-blue-300 rounded-lg text-lg">
        </div>
        <div class="flex gap-4">
            <button type="submit" class="bg-blue-900 text-white px-10 py-4 rounded-lg font-bold text-xl hover:bg-blue-800 shadow-lg">
                <i class="fas fa-filter mr-3"></i> FILTRAR
            </button>
            <a href="reportes.php?desde=<?= $fecha_desde ?>&hasta=<?= $fecha_hasta ?>&export=excel" 
               class="bg-green-600 text-white px-10 py-4 rounded-lg font-bold text-xl hover:bg-green-700 shadow-lg flex items-center">
                <i class="fas fa-file-excel mr-3"></i> EXCEL
            </a>
        </div>
    </form>
</div>

<!-- Tarjetas de estadísticas -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 mb-12">
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-8 border-blue-900">
        <p class="text-5xl font-bold text-blue-900"><?= $total ?></p>
        <p class="text-xl text-gray-700 mt-2">Total</p>
    </div>
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-8 border-green-600">
        <p class="text-5xl font-bold text-green-600"><?= $abiertos ?></p>
        <p class="text-xl text-gray-700 mt-2">Abiertos</p>
    </div>
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-8 border-yellow-600">
        <p class="text-5xl font-bold text-yellow-600"><?= $en_proceso ?></p>
        <p class="text-xl text-gray-700 mt-2">En Proceso</p>
    </div>
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-8 border-gray-600">
        <p class="text-5xl font-bold text-gray-600"><?= $cerrados ?></p>
        <p class="text-xl text-gray-700 mt-2">Cerrados</p>
    </div>
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center border-t-8 border-red-600">
        <p class="text-5xl font-bold text-red-600"><?= $urgentes ?></p>
        <p class="text-xl text-gray-700 mt-2">Urgentes</p>
    </div>
</div>

<!-- Tabla de tickets -->
<div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="p-6 text-left">Ticket</th>
                    <th class="p-6 text-left">Cliente</th>
                    <th class="p-6 text-left">Asunto</th>
                    <th class="p-6 text-left">Estado</th>
                    <th class="p-6 text-left">Prioridad</th>
                    <th class="p-6 text-left">Agente</th>
                    <th class="p-6 text-left">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $t): 
                    $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
                ?>
                <tr class="border-b hover:bg-blue-50 transition">
                    <td class="p-6 font-bold">#<?= htmlspecialchars($numero) ?></td>
                    <td class="p-6"><?= htmlspecialchars($t['cliente_email']) ?></td>
                    <td class="p-6"><?= htmlspecialchars($t['asunto']) ?></td>
                    <td class="p-6">
                        <span class="px-4 py-2 rounded-full text-sm font-bold <?= $t['estado']=='abierto'?'bg-green-200 text-green-800':($t['estado']=='cerrado'?'bg-gray-300':'bg-yellow-200 text-yellow-800') ?>">
                            <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                        </span>
                    </td>
                    <td class="p-6">
                        <span class="px-4 py-2 rounded-full text-sm font-bold <?= $t['prioridad']=='urgente'?'bg-red-200 text-red-800':($t['prioridad']=='alta'?'bg-orange-200 text-orange-800':'bg-blue-200 text-blue-800') ?>">
                            <?= ucfirst($t['prioridad']) ?>
                        </span>
                    </td>
                    <td class="p-6"><?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?></td>
                    <td class="p-6"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>