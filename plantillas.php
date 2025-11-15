<?php 
ob_start();
require 'config.php';
require 'header.php'; // ← AQUÍ SE CARGA $user

// VERIFICAR QUE EL USUARIO ESTÉ LOGUEADO Y TENGA PERMISO
if(!isset($user) || !in_array($user['rol'],['administrador','superadmin','agente'])) { 
    die('<div class="p-20 text-center text-red-600 text-5xl font-bold bg-white rounded-3xl shadow-3xl">Acceso denegado. Solo administradores y agentes.</div>'); 
}

// === CREAR / EDITAR PLANTILLA ===
$mensaje = '';
$error = '';
$editar = null;

if($_POST){
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje_plantilla = trim($_POST['mensaje'] ?? '');
    $id = intval($_POST['id'] ?? 0);

    if(empty($titulo) || empty($mensaje_plantilla)){
        $error = "Título y mensaje son obligatorios";
    } else {
        try {
            if($id > 0){
                $stmt = $pdo->prepare("UPDATE plantillas_respuesta SET titulo=?, mensaje=? WHERE id=?");
                $stmt->execute([$titulo, $mensaje_plantilla, $id]);
                $mensaje = "Plantilla actualizada correctamente";
            } else {
                $stmt = $pdo->prepare("INSERT INTO plantillas_respuesta (titulo, mensaje, creado_por) VALUES (?, ?, ?)");
                $stmt->execute([$titulo, $mensaje_plantilla, $user['id']]);
                $mensaje = "Plantilla creada correctamente";
            }
            header("Location: plantillas.php?msg=" . urlencode($mensaje));
            exit();
        } catch(Exception $e){
            $error = "Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// === ELIMINAR PLANTILLA ===
if(isset($_GET['eliminar'])){
    $id = intval($_GET['eliminar']);
    try {
        $stmt = $pdo->prepare("DELETE FROM plantillas_respuesta WHERE id=?");
        $stmt->execute([$id]);
        header("Location: plantillas.php?msg=Plantilla eliminada");
        exit();
    } catch(Exception $e){
        $error = "Error al eliminar: " . htmlspecialchars($e->getMessage());
    }
}

// === EDITAR PLANTILLA ===
if(isset($_GET['editar'])){
    $id = intval($_GET['editar']);
    $stmt = $pdo->prepare("SELECT * FROM plantillas_respuesta WHERE id=?");
    $stmt->execute([$id]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === CARGAR TODAS LAS PLANTILLAS ===
try {
    $stmt = $pdo->query("SELECT p.*, u.nombre as creador FROM plantillas_respuesta p LEFT JOIN users u ON p.creado_por = u.id ORDER BY p.creado_el DESC");
    $plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){
    $plantillas = [];
    $error = "Error al cargar plantillas: " . htmlspecialchars($e->getMessage());
}
?>

<div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 py-12 px-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-7xl font-bold text-white text-center mb-16 drop-shadow-2xl">
            PLANTILLAS DE RESPUESTA RÁPIDA
        </h1>

        <?php if(isset($_GET['msg'])): ?>
        <div class="bg-green-600 text-white px-20 py-12 rounded-3xl mb-16 text-4xl font-bold text-center shadow-3xl">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="bg-red-600 text-white px-20 py-12 rounded-3xl mb-16 text-4xl font-bold text-center shadow-3xl">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- FORMULARIO CREAR / EDITAR -->
        <div class="bg-white rounded-3xl shadow-3xl p-16 mb-20 border-12 border-blue-900">
            <h2 class="text-5xl font-bold text-blue-900 mb-12 text-center">
                <?= $editar ? 'EDITAR PLANTILLA' : 'NUEVA PLANTILLA' ?>
            </h2>
            <form method="POST" class="space-y-12">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Título de la plantilla</label>
                    <input type="text" name="titulo" value="<?= htmlspecialchars($editar['titulo'] ?? '') ?>" required 
                           class="w-full p-8 border-6 border-blue-300 rounded-3xl text-2xl focus:border-blue-900 transition shadow-2xl">
                </div>
                <div>
                    <label class="block text-2xl font-bold text-blue-900 mb-4">Mensaje (usa \n para saltos de línea)</label>
                    <textarea name="mensaje" rows="12" required 
                              class="w-full p-8 border-6 border-blue-300 rounded-3xl text-2xl focus:border-blue-900 transition resize-none shadow-2xl">
<?= htmlspecialchars($editar['mensaje'] ?? '') ?>
                    </textarea>
                </div>
                <div class="text-center space-x-16">
                    <button type="submit" 
                            class="bg-gradient-to-r from-green-600 to-green-500 text-white px-64 py-20 rounded-full text-5xl font-bold hover:from-green-700 hover:to-green-600 shadow-3xl transform hover:scale-110 transition">
                        <?= $editar ? 'ACTUALIZAR PLANTILLA' : 'CREAR PLANTILLA' ?>
                    </button>
                    <?php if($editar): ?>
                    <a href="plantillas.php" class="bg-gray-600 text-white px-64 py-20 rounded-full text-5xl font-bold hover:bg-gray-700 shadow-3xl inline-block">
                        CANCELAR
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA DE PLANTILLAS -->
        <?php if(!empty($plantillas)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
            <?php foreach($plantillas as $p): ?>
            <div class="bg-white rounded-3xl shadow-3xl p-12 border-12 border-teal-500 hover:border-teal-700 transition-all duration-300">
                <div class="flex justify-between items-start mb-8">
                    <h3 class="text-4xl font-bold text-teal-800"><?= htmlspecialchars($p['titulo']) ?></h3>
                    <div class="space-x-6">
                        <a href="plantillas.php?editar=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-800 text-3xl">
                            Editar
                        </a>
                        <a href="plantillas.php?eliminar=<?= $p['id'] ?>" onclick="return confirm('¿Eliminar esta plantilla?')" class="text-red-600 hover:text-red-800 text-3xl">
                            Eliminar
                        </a>
                    </div>
                </div>
                <div class="bg-gray-50 p-8 rounded-2xl mb-8 border-4 border-gray-300">
                    <p class="text-xl text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($p['mensaje']) ?></p>
                </div>
                <small class="text-gray-500 block text-center">
                    Creado por: <?= htmlspecialchars($p['creador'] ?? 'Sistema') ?> 
                    <br><?= date('d/m/Y H:i', strtotime($p['creado_el'])) ?>
                </small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-yellow-100 border-8 border-yellow-600 text-yellow-800 px-20 py-16 rounded-3xl text-4xl font-bold text-center shadow-3xl">
            No hay plantillas creadas aún
        </div>
        <?php endif; ?>

        <div class="text-center mt-20">
            <a href="tickets.php" class="text-blue-300 hover:text-white text-3xl underline">
                Volver a Tickets
            </a>
        </div>
    </div>
</div>

<?php 
ob_end_flush();
require 'footer.php'; 
?>