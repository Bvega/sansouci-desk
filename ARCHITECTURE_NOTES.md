# Sansouci Desk – Notas de Arquitectura (Refactor)

## Rutas y entry points actuales

- `index.php`  
  - Ruta: `C:\xampp\htdocs\sansouci-desk\index.php`  
  - Portal público de clientes. Formulario para crear tickets.

- `login.php`  
  - Ruta: `C:\xampp\htdocs\sansouci-desk\login.php`  
  - Pantalla de login para panel administrativo.

- `admin\dashboard.php`  
  - Ruta: `C:\xampp\htdocs\sansouci-desk\admin\dashboard.php`  
  - Pantalla principal del panel.

- `tickets.php`, `ticket1.php`, etc.  
  - Gestión de tickets desde el panel.

- `config.php`  
  - Conexión PDO a la base de datos (usa `config.local.php`).

- `config.local.php`  
  - Config específica del entorno local (XAMPP).

## Nueva estructura para refactor (no usada todavía)

- `app\Core\Database.php`  
  - Clase central para obtener conexión PDO sin usar variables globales.

- `app\bootstrap.php`  
  - Autoload simple para clases dentro del namespace `App\`.
  - Inicializa sesión y configuración base.

- `app\Controllers\`  
  - Aquí irán los controladores (login, tickets, usuarios, etc.).

- `app\Models\`  
  - Aquí irán los modelos que hablan con la BD usando `App\Core\Database`.

- `app\Views\`  
  - Plantillas de presentación (HTML + algo de PHP mínimo).

## Plan de migración gradual

1. Mantener funcional el código legado.
2. Crear Models para entidades clave:
   - `UserModel` (login, gestión usuarios)
   - `TicketModel` (alta, listado, asignación)
3. Crear Controllers que usen esos models.
4. Reemplazar poco a poco la lógica de archivos como `login.php`, `tickets.php`, etc., para que deleguen en los Controllers/Models nuevos.
5. Al final, considerar un front controller (por ejemplo `public/index.php`) y URLs limpias.
