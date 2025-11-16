<?php
require __DIR__ . '/config.php';
require __DIR__ . '/app/bootstrap.php';

// Si por alguna razón se llega sin usuario, redirigimos (doble seguridad)
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Total de tickets (opcional)
$totalTickets = null;
try {
    if (isset($pdo)) {
        $stmt = $pdo->query('SELECT COUNT(*) AS total FROM tickets');
        $row = $stmt->fetch();
        $totalTickets = $row ? (int)$row['total'] : 0;
    }
} catch (Throwable $e) {
    $totalTickets = null;
}

include __DIR__ . '/header.php';
?>

<h1 class="text-3xl font-bold mb-6">Dashboard</h1>

<p class="mb-4">
    Bienvenido, <strong><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
</p>

<?php if ($totalTickets !== null): ?>
    <div class="p-4 rounded bg-blue-50 border border-blue-200 inline-block">
        <p class="text-xl font-semibold">Total de tickets: <?= $totalTickets ?></p>
    </div>
<?php else: ?>
    <p class="text-gray-600">
        (No se pudo obtener el total de tickets, pero el panel está funcionando.)
    </p>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
