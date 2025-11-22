<?php
ob_start();
require 'header.php'; // aquí ya tenemos $pdo y $user

if (!$user) {
    header('Location: login.php');
    exit();
}

$mensaje_error = '';
$mensaje_ok    = '';
$modo_edicion  = false;
$plantilla_editar = [
    'id'        => 0,
    'titulo'    => '',
    'contenido' => '',
];

/* ===============================
   1. PROCESAR ACCIONES (POST/GET)
   =============================== */

// Guardar / actualizar plantilla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');

    if ($titulo === '' || $contenido === '') {
        $mensaje_error = 'El título y el contenido son obligatorios.';
    } else {
        if ($id > 0) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE plantillas_respuesta
                SET titulo = ?, contenido = ?
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $contenido, $id]);
            header('Location: plantillas.php?msg=Plantilla+actualizada+correctamente');
            exit();
        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO plantillas_respuesta (titulo, contenido, creado_el)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$titulo, $contenido]);
            header('Location: plantillas.php?msg=Plantilla+creada+correctamente');
            exit();
        }
    }
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM plantillas_respuesta WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: plantillas.php?msg=Plantilla+eliminada+correctamente');
        exit();
    }
}

// Cargar datos para edición
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT id, titulo, contenido
            FROM plantillas_respuesta
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $modo_edicion     = true;
            $plantilla_editar = $row;
        }
    }
}

// Mensaje de la URL (?msg=...)
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $mensaje_ok = $_GET['msg'];
}

/* ===============================
   2. CARGAR LISTA DE PLANTILLAS
   =============================== */

$stmt = $pdo->query("
    SELECT id, titulo, contenido, creado_el
    FROM plantillas_respuesta
    ORDER BY creado_el DESC
");
$plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-blue-900">Respuestas Rápidas</h1>
        <p class="text-gray-600 text-sm mt-1">
            Administra plantillas de respuesta para usar al atender tickets desde el panel de
            <span class="font-semibold">Responder</span>.
        </p>
    </div>

    <?php if ($mensaje_error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-xl text-sm">
            <?= htmlspecialchars($mensaje_error) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_ok): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-xl text-sm">
            <?= htmlspecialchars($mensaje_ok) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- LISTADO DE PLANTILLAS -->
        <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col">
            <div class="flex items-center justify-between mb  -4">
                <h2 class="text-xl font-bold text-blue-900">Listado de plantillas</h2>
                <a href="plantillas.php"
                   class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Limpiar selección
                </a>
            </div>

            <div class="mt-4 space-y-3 max-h-[480px] overflow-y-auto pr-1">
                <?php if (empty($plantillas)): ?>
                    <p class="text-gray-500 text-sm">Aún no hay plantillas creadas.</p>
                <?php else: ?>
                    <?php foreach ($plantillas as $p): ?>
                        <div class="border border-gray-200 rounded-xl p-3 flex items-start justify-between bg-gray-50">
                            <div class="mr-3">
                                <p class="font-semibold text-sm text-blue-900">
                                    ID #<?= (int)$p['id'] ?> ·
                                    <?= htmlspecialchars($p['titulo']) ?>
                                </p>
                                <p class="text-[11px] text-gray-500 mt-1">
                                    <?= htmlspecialchars($p['creado_el']) ?>
                                </p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <a href="plantillas.php?editar=<?= (int)$p['id'] ?>"
                                   class="px-3 py-1 text-xs rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-center">
                                    Editar
                                </a>
                                <button type="button"
                                        class="px-3 py-1 text-xs rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 copiar-btn"
                                        data-texto="<?= htmlspecialchars($p['contenido']) ?>">
                                    Copiar texto
                                </button>
                                <a href="plantillas.php?eliminar=<?= (int)$p['id'] ?>"
                                   onclick="return confirm('¿Eliminar esta plantilla?')"
                                   class="px-3 py-1 text-xs rounded-lg bg-red-500 text-white hover:bg-red-600 text-center">
                                    Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- FORMULARIO CREAR / EDITAR -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-blue-900">
                    <?= $modo_edicion ? 'Editar plantilla' : 'Crear nueva plantilla' ?>
                </h2>
                <?php if ($modo_edicion): ?>
                    <span class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-800 font-semibold">
                        Editando ID #<?= (int)$plantilla_editar['id'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= (int)$plantilla_editar['id'] ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Título de la plantilla
                    </label>
                    <input
                        type="text"
                        name="titulo"
                        value="<?= htmlspecialchars($plantilla_editar['titulo']) ?>"
                        placeholder="Ej: Ticket recibido, En proceso, Resuelto, Contactar administración"
                        class="w-full border-2 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Contenido
                    </label>
                    <textarea
                        name="contenido"
                        rows="7"
                        class="w-full border-2 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Escribe el texto que quieres reutilizar al responder tickets..."
                    ><?= htmlspecialchars($plantilla_editar['contenido']) ?></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-2 text-[11px] text-gray-500">
                    <p>
                        Consejo: usa un lenguaje neutro. Luego podrás copiar este texto y pegarlo en la respuesta
                        del ticket desde el panel de <span class="font-semibold">Responder</span>.
                    </p>

                    <div class="flex gap-2">
                        <?php if ($modo_edicion): ?>
                            <a href="plantillas.php"
                               class="px-4 py-2 rounded-xl bg-gray-200 text-gray-800 font-semibold text-xs hover:bg-gray-300">
                                Cancelar edición
                            </a>
                        <?php endif; ?>
                        <button type="submit"
                                class="px-5 py-2 rounded-xl bg-green-600 text-white font-semibold text-xs hover:bg-green-700">
                            Guardar plantilla
                        </button>
                    </div>
                </div>

                <!-- Variables rápidas (placeholders) -->
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="text-gray-500 mr-2">Insertar variables rápidas:</span>
                    <button type="button" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 insertar-placeholder"
                            data-placeholder="{{ticket_numero}}">
                        {{ticket_numero}}
                    </button>
                    <button type="button" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 insertar-placeholder"
                            data-placeholder="{{ticket_asunto}}">
                        {{ticket_asunto}}
                    </button>
                    <button type="button" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 insertar-placeholder"
                            data-placeholder="{{cliente_email}}">
                        {{cliente_email}}
                    </button>
                    <button type="button" class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 insertar-placeholder"
                            data-placeholder="{{estado_ticket}}">
                        {{estado_ticket}}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Copiar texto al portapapeles
document.querySelectorAll('.copiar-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const texto = btn.getAttribute('data-texto') || '';
        if (!texto) return;
        navigator.clipboard.writeText(texto).then(() => {
            btn.textContent = 'Copiado';
            setTimeout(() => btn.textContent = 'Copiar texto', 1500);
        });
    });
});

// Insertar placeholders en el textarea de contenido
const textareaContenido = document.querySelector('textarea[name="contenido"]');
document.querySelectorAll('.insertar-placeholder').forEach(btn => {
    btn.addEventListener('click', () => {
        if (!textareaContenido) return;
        const placeholder = btn.getAttribute('data-placeholder');
        const start = textareaContenido.selectionStart || 0;
        const end   = textareaContenido.selectionEnd || 0;
        const value = textareaContenido.value;

        textareaContenido.value =
            value.substring(0, start) +
            placeholder +
            value.substring(end);

        textareaContenido.focus();
        textareaContenido.selectionStart = textareaContenido.selectionEnd =
            start + placeholder.length;
    });
});
</script>

<?php
ob_end_flush();
require 'footer.php';
?>

