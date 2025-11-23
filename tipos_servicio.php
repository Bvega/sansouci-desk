<?php
ob_start();
require 'header.php'; // aquí ya tenemos $pdo y $user

// 1. Proteger la página: solo admin y superadmin
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'], true)) {
    echo '<div class="p-10 text-2xl text-red-700 font-bold">No tienes permisos para administrar los tipos de servicio.</div>';
    require 'footer.php';
    ob_end_flush();
    exit();
}

$mensaje = '';
$mensaje_tipo = 'ok';

// 2. Procesar formulario (crear / actualizar / eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion      = $_POST['accion'] ?? '';
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    // Validación básica
    if (in_array($accion, ['guardar', 'actualizar'], true) && $nombre === '') {
        $mensaje = 'El nombre del tipo de servicio es obligatorio.';
        $mensaje_tipo = 'error';
    } else {
        try {
            if ($accion === 'guardar') {
                $stmt = $pdo->prepare("
                    INSERT INTO tipos_servicio (nombre, descripcion)
                    VALUES (?, ?)
                ");
                $stmt->execute([$nombre, $descripcion]);
                $mensaje = 'Tipo de servicio creado correctamente.';
            }

            if ($accion === 'actualizar' && $id) {
                $stmt = $pdo->prepare("
                    UPDATE tipos_servicio
                    SET nombre = ?, descripcion = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $descripcion, $id]);
                $mensaje = 'Tipo de servicio actualizado correctamente.';
            }

            if ($accion === 'eliminar' && $id) {
                // OJO: si en el futuro quieres evitar eliminar tipos usados en tickets,
                // aquí podríamos validar que no existan tickets con ese tipo.
                $stmt = $pdo->prepare("DELETE FROM tipos_servicio WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'Tipo de servicio eliminado.';
            }
        } catch (Exception $e) {
            $mensaje = 'Ocurrió un error al guardar la información.';
            $mensaje_tipo = 'error';
        }
    }
}

// 3. Si viene ?id=xx en GET, cargamos ese tipo para editar
$edit_tipo = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion FROM tipos_servicio WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $edit_tipo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// 4. Listar todos los tipos de servicio
$stmt = $pdo->query("
    SELECT id, nombre, descripcion
    FROM tipos_servicio
    ORDER BY nombre ASC
");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<div class="flex flex-col gap-8">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-4xl font-bold text-blue-900">Tipos de Servicio</h1>
            <p class="text-gray-600 text-lg">
                Define y administra los tipos de servicio que usarán los tickets
                y las reglas de asignación automática.
            </p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="<?= $mensaje_tipo === 'error'
            ? 'bg-red-100 border-l-4 border-red-500 text-red-800'
            : 'bg-green-100 border-l-4 border-green-500 text-green-800'
        ?> p-4 rounded-xl text-lg">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- LISTA DE TIPOS DE SERVICIO -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Listado de tipos</h2>

            <?php if (empty($tipos)): ?>
                <p class="text-gray-500 text-lg">Todavía no hay tipos de servicio configurados.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($tipos as $t): ?>
                        <div class="border border-gray-200 rounded-xl p-4 flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-lg text-blue-900">
                                    <?= htmlspecialchars($t['nombre']) ?>
                                </p>
                                <?php if (!empty($t['descripcion'])): ?>
                                    <p class="text-gray-600 text-sm mt-1">
                                        <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="tipos_servicio.php?id=<?= (int)$t['id'] ?>"
                                   class="px-4 py-2 text-sm font-bold rounded-xl bg-blue-100 text-blue-800 hover:bg-blue-200">
                                    Editar
                                </a>
                                <form method="POST"
                                      onsubmit="return confirm('¿Eliminar este tipo de servicio?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit"
                                            class="px-4 py-2 text-sm font-bold rounded-xl bg-red-100 text-red-700 hover:bg-red-200">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- FORMULARIO CREAR / EDITAR -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">
                <?= $edit_tipo ? 'Editar tipo de servicio' : 'Crear nuevo tipo de servicio' ?>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion"
                       value="<?= $edit_tipo ? 'actualizar' : 'guardar' ?>">
                <?php if ($edit_tipo): ?>
                    <input type="hidden" name="id" value="<?= (int)$edit_tipo['id'] ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-lg font-semibold mb-2">Nombre del tipo</label>
                    <input type="text"
                           name="nombre"
                           value="<?= htmlspecialchars($edit_tipo['nombre'] ?? '') ?>"
                           class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ej: Consulta General, Reservas, Facturación...">
                </div>

                <div>
                    <label class="block text-lg font-semibold mb-2">Descripción (opcional)</label>
                    <textarea name="descripcion" rows="4"
                              class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Descripción breve de este tipo de servicio..."><?= htmlspecialchars($edit_tipo['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="pt-2 flex items-center gap-4">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl text-lg shadow-lg">
                        <?= $edit_tipo ? 'Actualizar tipo' : 'Crear tipo' ?>
                    </button>

                    <?php if ($edit_tipo): ?>
                        <a href="tipos_servicio.php"
                           class="text-blue-700 font-semibold">
                            Cancelar edición
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require 'footer.php';
ob_end_flush();
?>
