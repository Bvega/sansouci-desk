<?php require 'header.php'; 

// OBTENER IDS
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
    $estado = $_POST['estado'];
    $prioridad = $_POST['prioridad'];
    $tipo_servicio = $_POST['tipo_servicio'];
    $agente_id = $_POST['agente_id'] ?: null;
    $respuesta = trim($_POST['respuesta'] ?? '');

    $in = str_repeat('?,', count($ids) - 1) . '?';
    
    // ACTUALIZAR TICKETS
    $stmt = $pdo->prepare("UPDATE tickets SET estado=?, prioridad=?, tipo_servicio=?, agente_id=? WHERE id IN ($in)");
    $params = [$estado, $prioridad, $tipo_servicio, $agente_id];
    $params = array_merge($params, $ids);
    $stmt->execute($params);

    // AGREGAR RESPUESTA A CADA TICKET
    if(!empty($respuesta)){
        $stmt_resp = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                                    VALUES (?, ?, 'agente', ?, NOW())");
        foreach($ids as $id){
            $stmt_resp->execute([$id, $respuesta, $user['email']]);
        }
    }

    header('Location: tickets.php?msg=' . urlencode(count($ids) . " tickets actualizados"));
    exit();
}

// CARGAR DATOS
$agentes = $pdo->query("SELECT id, nombre FROM users WHERE rol = 'agente' ORDER BY nombre")->fetchAll();
$tipos = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre")->fetchAll();

$in = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id IN ($in)");
$stmt->execute($ids);
$tickets_seleccionados = $stmt->fetchAll();
?>
<h1 class="text-4xl font-bold text-blue-900 mb-10">Modificar <?= count($ids) ?> Ticket(s) Seleccionados</h1>

<div class="bg-white rounded-2xl shadow-2xl p-10 max-w-7xl">
    <!-- LISTA DE TICKETS CON HISTORIAL -->
    <div class="space-y-12 mb-16">
        <?php foreach($tickets_seleccionados as $t): 
            $numero = $t['numero'] ?? 'TCK-'.str_pad($t['id'],5,'0',STR_PAD_LEFT);
        ?>
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-xl p-10 border-l-8 border-blue-900">
            <div class="flex justify-between items-start mb-8">
                <div>
                    <span class="text-3xl font-bold text-blue-900">#<?= htmlspecialchars($numero) ?></span>
                    <span class="ml-6 px-8 py-4 rounded-full text-xl font-bold <?= $t['estado']=='abierto'?'bg-green-200 text-green-800':($t['estado']=='cerrado'?'bg-gray-400 text-white':'bg-yellow-200 text-yellow-800') ?>">
                        <?= ucfirst(str_replace('_',' ',$t['estado'])) ?>
                    </span>
                </div>
                <small class="text-gray-600 text-xl"><?= date('d/m/Y H:i', strtotime($t['creado_el'])) ?></small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div>
                    <p class="text-lg"><strong>Cliente:</strong> <?= htmlspecialchars($t['cliente_email']) ?></p>
                    <p class="text-lg"><strong>Asunto:</strong> <?= htmlspecialchars($t['asunto']) ?></p>
                    <p class="text-lg"><strong>Tipo:</strong> <?= htmlspecialchars($t['tipo_servicio'] ?? 'General') ?></p>
                    <p class="text-lg"><strong>Agente:</strong> <?= htmlspecialchars($t['agente_nombre'] ?? 'Sin asignar') ?></p>
                </div>
                <div>
                    <p class="text-lg"><strong>Mensaje original:</strong></p>
                    <p class="bg-white p-6 rounded-xl shadow-inner text-gray-700"><?= nl2br(htmlspecialchars($t['mensaje'])) ?></p>
                </div>
            </div>

            <!-- HISTORIAL DE RESPUESTAS -->
            <?php 
            $stmt_resp = $pdo->prepare("SELECT * FROM respuestas WHERE ticket_id = ? ORDER BY creado_el");
            $stmt_resp->execute([$t['id']]);
            $respuestas = $stmt_resp->fetchAll();
            if(!empty($respuestas)): ?>
            <div class="border-t-4 border-blue-300 pt-8">
                <h4 class="text-2xl font-bold text-blue-900 mb-6">Historial de Conversación</h4>
                <div class="space-y-6">
                    <?php foreach($respuestas as $r): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-6 <?= $r['autor']=='cliente'?'border-green-600':'border-blue-600' ?>">
                        <div class="flex justify-between items-start mb-4">
                            <span class="font-bold text-xl <?= $r['autor']=='cliente'?'text-green-800':'text-blue-800' ?>">
                                <?= $r['autor']=='cliente'?'Cliente':'Agente' ?> 
                                <?= $r['autor']=='agente' ? '('.htmlspecialchars($r['autor_email']).')' : '' ?>
                            </span>
                            <small class="text-gray-600"><?= date('d/m/Y H:i', strtotime($r['creado_el'])) ?></small>
                        </div>
                        <p class="text-lg text-gray-700"><?= nl2br(htmlspecialchars($r['mensaje'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <p class="text-gray-500 italic">No hay respuestas aún</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FORMULARIO DE MODIFICACIÓN MASIVA -->
    <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-2xl p-12 shadow-2xl border-4 border-purple-200">
        <h3 class="text-3xl font-bold text-blue-900 mb-10 text-center">
            <i class="fas fa-cogs mr-6"></i> APLICAR CAMBIOS A TODOS LOS TICKETS
        </h3>
        <form method="POST" class="space-y-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-6">
                        <i class="fas fa-sync mr-4"></i> Estado
                    </label>
                    <select name="estado" class="w-full p-8 border-6 border-blue-400 rounded-3xl text-2xl font-bold bg-white shadow-xl">
                        <option value="abierto">Abierto</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-6">
                        <i class="fas fa-exclamation-triangle mr-4"></i> Prioridad
                    </label>
                    <select name="prioridad" class="w-full p-8 border-6 border-orange-400 rounded-3xl text-2xl font-bold bg-white shadow-xl">
                        <option value="normal">Normal</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-6">
                        <i class="fas fa-concierge-bell mr-4"></i> Tipo de Servicio
                    </label>
                    <select name="tipo_servicio" class="w-full p-8 border-6 border-purple-400 rounded-3xl text-2xl font-bold bg-white shadow-xl">
                        <?php foreach($tipos as $tipo): ?>
                        <option value="<?= htmlspecialchars($tipo['nombre']) ?>">
                            <?= htmlspecialchars($tipo['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-6">
                        <i class="fas fa-user-tie mr-4"></i> Agente Asignado
                    </label>
                    <select name="agente_id" class="w-full p-8 border-6 border-teal-400 rounded-3xl text-2xl font-bold bg-white shadow-xl">
                        <option value="">Sin asignar</option>
                        <?php foreach($agentes as $a): ?>
                        <option value="<?= $a['id'] ?>">
                            <?= htmlspecialchars($a['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- RESPUESTA MASIVA -->
            <div>
                <label class="block text-3xl font-bold text-blue-900 mb-8">
                    <i class="fas fa-comment-dots mr-6"></i> Respuesta para todos los clientes (opcional)
                </label>
                <textarea name="respuesta" rows="10" 
                          class="w-full p-10 border-8 border-blue-500 rounded-3xl text-2xl focus:border-blue-700 transition resize-none shadow-2xl bg-gradient-to-b from-white to-blue-50"
                          placeholder="Este mensaje se enviará a todos los clientes seleccionados..."></textarea>
            </div>

            <div class="text-center pt-16 space-x-20">
                <button type="submit" class="bg-green-600 text-white px-48 py-20 rounded-full text-6xl font-bold hover:bg-green-700 shadow-3xl transform hover:scale-110 transition">
                    <i class="fas fa-save mr-12"></i> APLICAR A TODOS
                </button>
                <a href="tickets.php" class="bg-gray-600 text-white px-48 py-20 rounded-full text-6xl font-bold hover:bg-gray-700 inline-block">
                    CANCELAR
                </a>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>