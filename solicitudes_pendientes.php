<?php
// solicitudes_pendientes.php - Listado de solicitudes pendientes para usuario de almacén
require_once 'config.php';
verificarSesion();
verificarRol(['almacen']);

$db = conectarDB();

// Obtener solicitudes pendientes
$query = "SELECT s.*, u.nombre, u.apellido, u.departamento, COUNT(p.id) as num_partidas
          FROM solicitudes s
          JOIN usuarios u ON s.usuario_id = u.id
          LEFT JOIN partidas_solicitud p ON s.id = p.solicitud_id
          WHERE s.estatus = 'pendiente'
          GROUP BY s.id
          ORDER BY s.fecha_solicitud ASC";

$resultado = $db->query($query);
$solicitudes = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}

// Procesar solicitudes seleccionadas para generar requisición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_requisicion'])) {
    if (!isset($_POST['solicitudes']) || !is_array($_POST['solicitudes']) || empty($_POST['solicitudes'])) {
        mostrarAlerta('warning', 'Debe seleccionar al menos una solicitud');
    } else {
        // Almacenar IDs de solicitudes en sesión para procesarlos en la página de requisición
        $_SESSION['solicitudes_seleccionadas'] = $_POST['solicitudes'];
        header('Location: requisicion_nueva.php');
        exit;
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Solicitudes Pendientes</h1>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-gray-800">Listado de Solicitudes</h5>
        
        <?php if (!empty($solicitudes)): ?>
        <form method="POST" id="formSeleccion">
            <button type="submit" name="generar_requisicion" class="btn btn-primary" id="btnGenerarRequisicion" disabled>
                <i class="bi bi-file-earmark-plus"></i> Generar Requisición
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($solicitudes)): ?>
            <div class="alert alert-info">
                No hay solicitudes pendientes en este momento.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Departamento</th>
                            <th>Destinado a</th>
                            <th>Fecha Solicitud</th>
                            <th>Deseado para</th>
                            <th>Partidas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input solicitud-check" type="checkbox" name="solicitudes[]" value="<?php echo $solicitud['id']; ?>" form="formSeleccion">
                                    </div>
                                </td>
                                <td><?php echo $solicitud['id']; ?></td>
                                <td><?php echo htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['departamento']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['destinado_a']); ?></td>
                                <td><?php echo formatearFecha($solicitud['fecha_solicitud']); ?></td>
                                <td><?php echo formatearFecha($solicitud['deseado_para']); ?></td>
                                <td><span class="badge bg-info"><?php echo $solicitud['num_partidas']; ?></span></td>
                                <td>
                                    <a href="solicitud_detalle.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Seleccionar todas las solicitudes
        $('#selectAll').change(function() {
            $('.solicitud-check').prop('checked', $(this).is(':checked'));
            actualizarBotonRequisicion();
        });
        
        // Actualizar estado del botón cuando se selecciona/deselecciona una solicitud
        $('.solicitud-check').change(function() {
            actualizarBotonRequisicion();
            
            // Actualizar "Seleccionar todos" si es necesario
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            } else if ($('.solicitud-check:checked').length === $('.solicitud-check').length) {
                $('#selectAll').prop('checked', true);
            }
        });
        
        // Función para habilitar/deshabilitar botón de generar requisición
        function actualizarBotonRequisicion() {
            $('#btnGenerarRequisicion').prop('disabled', $('.solicitud-check:checked').length === 0);
        }
        
        // Comprobar el estado inicial
        actualizarBotonRequisicion();
    });
</script>

<?php include 'footer.php'; ?>