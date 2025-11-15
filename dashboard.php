<?php require 'header.php'; ?>
<!-- Tu contenido -->
<h1>Bienvenido, <?= htmlspecialchars($user['nombre']) ?></h1>

<!-- Tarjetas de tickets por agente -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
    <?php
    $stmt = $pdo->query("SELECT u.nombre, COUNT(t.id) as total FROM users u LEFT JOIN tickets t ON u.id = t.agente_id AND t.estado = 'abierto' WHERE u.rol = 'agente' GROUP BY u.id");
    $total_abiertos = 0;
    while($row = $stmt->fetch()){
        $total_abiertos += $row['total'];
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600">
        <h3 class="font-semibold text-gray-700"><?= $row['nombre'] ?></h3>
        <p class="text-3xl font-bold text-blue-900 mt-2"><?= $row['total'] ?></p>
        <small class="text-gray-500">tickets abiertos</small>
    </div>
    <?php } ?>
    <div class="bg-blue-900 text-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-400">
        <h3 class="font-semibold">TOTAL ABIERTOS</h3>
        <p class="text-4xl font-bold mt-2"><?= $total_abiertos ?></p>
        <small>en todo el sistema</small>
    </div>
</div>

<!-- Gráfico de tendencia -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-2xl font-bold text-blue-900 mb-4">Tendencia Últimos 30 Días</h2>
    <canvas id="tendenciaChart" height="100"></canvas>
</div>

<script>
const ctx = document.getElementById('tendenciaChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Hace 30d', 'Hace 20d', 'Hace 10d', 'Hoy'],
        datasets: [
            { label: 'Abiertos', data: [12, 19, 15, <?= $total_abiertos ?>], borderColor: '#003087', tension: 0.4 },
            { label: 'Cerrados', data: [8, 15, 25, 38], borderColor: '#10B981', tension: 0.4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } } }
});
</script>

<?php require 'footer.php'; ?>