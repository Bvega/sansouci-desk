<?php require 'header.php'; 
if($user['rol'] != 'superadmin') { die("Acceso denegado"); }

$stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
$config = $stmt->fetch() ?: ['smtp_host'=>'smtp.gmail.com','smtp_port'=>587,'notificar_usuarios'=>1];

if($_POST){
    $stmt = $pdo->prepare("INSERT INTO config_email (smtp_host,smtp_port,smtp_user,smtp_pass,smtp_from_email,smtp_from_name,notificar_usuarios) 
                           VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE 
                           smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port), smtp_user=VALUES(smtp_user), 
                           smtp_pass=VALUES(smtp_pass), smtp_from_email=VALUES(smtp_from_email), 
                           smtp_from_name=VALUES(smtp_from_name), notificar_usuarios=VALUES(notificar_usuarios)");
    $stmt->execute([
        $_POST['smtp_host'], $_POST['smtp_port'], $_POST['smtp_user'], 
        $_POST['smtp_pass'], $_POST['smtp_from_email'], $_POST['smtp_from_name'],
        isset($_POST['notificar_usuarios']) ? 1 : 0
    ]);
    $msg = "Configuración guardada correctamente";
    $config = array_merge($config, $_POST);
}
?>
<h1 class="text-4xl font-bold text-blue-900 mb-10">Configuración de Envío de Correo</h1>

<?php if(isset($msg)): ?>
<div class="bg-green-100 border-4 border-green-600 text-green-800 px-8 py-6 rounded-2xl mb-10 text-2xl font-bold">
    <?= $msg ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-2xl p-10">
    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <label class="block text-xl font-bold mb-3">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($config['smtp_host']) ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-xl font-bold mb-3">Puerto</label>
                <input type="number" name="smtp_port" value="<?= $config['smtp_port'] ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-xl font-bold mb-3">Usuario (email)</label>
                <input type="email" name="smtp_user" value="<?= htmlspecialchars($config['smtp_user'] ?? '') ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-xl font-bold mb-3">Contraseña / App Password</label>
                <input type="password" name="smtp_pass" value="<?= htmlspecialchars($config['smtp_pass'] ?? '') ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-xl font-bold mb-3">From Email</label>
                <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($config['smtp_from_email']) ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
            <div>
                <label class="block text-xl font-bold mb-3">From Name</label>
                <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($config['smtp_from_name']) ?>" required 
                       class="w-full p-4 border-2 rounded-lg text-lg">
            </div>
        </div>

        <div class="flex items-center space-x-4 mt-10">
            <input type="checkbox" name="notificar_usuarios" id="notif" <?= $config['notificar_usuarios'] ? 'checked' : '' ?> 
                   class="w-8 h-8 text-blue-900">
            <label for="notif" class="text-2xl font-bold">Notificar a todos los usuarios cuando se cree un ticket</label>
        </div>

        <div class="text-center mt-12">
            <button type="submit" class="bg-green-600 text-white px-20 py-8 rounded-xl text-3xl font-bold hover:bg-green-700 shadow-2xl">
                GUARDAR CONFIGURACIÓN
            </button>
        </div>
    </form>
</div>

<?php require 'footer.php'; ?>