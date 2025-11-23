<?php require 'header.php'; 
if($user['rol'] != 'superadmin' && $user['rol'] != 'administrador') die("Acceso denegado");

$mensaje = '';

if($_POST){
    if(isset($_POST['crear'])){
        $nombre = trim($_POST['nombre']);
        if(!empty($nombre)){
            $stmt = $pdo->prepare("INSERT INTO tipos_servicio (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            $mensaje = "Tipo de servicio creado";
        }
    }
    if(isset($_POST['eliminar'])){
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM tipos_servicio WHERE id = ?")->execute([$id]);
        $mensaje = "Tipo eliminado";
    }
}

$tipos = $pdo->query("SELECT * FROM tipos_servicio ORDER BY nombre")->fetchAll();
?>
<h1 class="text-4xl font-bold text-blue-900 mb-10">Tipos de Servicio</h1>

<?php if($mensaje): ?>
<div class="bg-green-100 border-4 border-green-600 text-green-800 px-8 py-6 rounded-2xl mb-10 text-2xl font-bold">
    <?= $mensaje ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-2xl p-10 mb-10">
    <h2 class="text-3xl font-bold text-blue-900 mb-8">Crear Nuevo Tipo</h2>
    <form method="POST" class="flex gap-6">
        <input type="text" name="nombre" required placeholder="Ej: Soporte de Red" 
               class="flex-1 p-6 border-4 border-blue-300 rounded-2xl text-2xl">
        <button name="crear" class="bg-green-600 text-white px-12 py-6 rounded-2xl text-2xl font-bold hover:bg-green-700">
            <i class="fas fa-plus mr-4"></i> CREAR
        </button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <table class="w-full">
        <thead class="bg-blue-900 text-white">
            <tr>
                <th class="p-8 text-left text-2xl">Nombre del Servicio</th>
                <th class="p-8 text-center text-2xl">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($tipos as $t): ?>
            <tr class="border-b-4 border-blue-100 hover:bg-blue-50">
                <td class="p-8 text-2xl font-bold text-blue-900"><?= htmlspecialchars($t['nombre']) ?></td>
                <td class="p-8 text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button name="eliminar" onclick="return confirm('¿Eliminar este tipo?')" 
                                class="bg-red-600 text-white px-10 py-5 rounded-xl text-xl font-bold hover:bg-red-700">
                            <i class="fas fa-trash mr-3"></i> ELIMINAR
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require 'footer.php'; ?>