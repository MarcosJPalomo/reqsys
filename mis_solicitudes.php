<?php
// mis_solicitudes.php - Listado de solicitudes para usuarios solicitantes
require_once 'config.php';
verificarSesion();
verificarRol(['solicitante']);

$db = conectarDB();
$usuario_id = (int)$_SESSION['usuario_id'];

// Obtener solicitudes del usuario actual
$query = "SELECT s.*, 
          COUNT(p.id) as num_partidas, 
          CASE 
            WHEN s.estatus = 'pendiente' THEN 'Pendiente'
            WHEN s.estatus = 'procesada' THEN 'Procesada'
            WHEN s.estatus = 'rechazada' THEN 'Rechazada'
          END as estatus_texto
          FROM solicitudes s
          LEFT JOIN partidas_solicitud p ON s.id = p.solicitud_id
          WHERE s.usuario_id = $usuario_id
          GROUP BY s.id
          ORDER BY s.fecha_solicitud DESC";

$resultado = $db->query($query);
$solicitudes = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Mis Solicitudes</h1>
    <a href="solicitud_nueva.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nueva Solicitud
    </a>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-gray-800">Listado de Solicitudes</h5>
    </div>
    <div class="card-body">
        <?php if (empty($solicitudes)): ?>
            <div class="alert alert-info">
                No hay solicitudes registradas. Crea una nueva solicitud para comenzar.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Folio</th>
                            <th>Destinado a</th>
                            <th>Fecha Solicitud</th>
                            <th>Deseado para</th>
                            <th>Partidas</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td><?php echo $solicitud['id']; ?></td>
                                <td><?php echo htmlspecialchars($solicitud['destinado_a']); ?></td>
                                <td><?php echo formatearFecha($solicitud['fecha_solicitud']); ?></td>
                                <td><?php echo formatearFecha($solicitud['deseado_para']); ?></td>
                                <td><span class="badge bg-info"><?php echo $solicitud['num_partidas']; ?></span></td>
                                <td>
                                    <?php 
                                    $class = '';
                                    switch ($solicitud['estatus']) {
                                        case 'pendiente':
                                            $class = 'bg-warning';
                                            break;
                                        case 'procesada':
                                            $class = 'bg-success';
                                            break;
                                        case 'rechazada':
                                            $class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $class; ?>"><?php echo $solicitud['estatus_texto']; ?></span>
                                </td>
                                <td>
                                    <a href="solicitud_detalle.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($solicitud['estatus'] === 'pendiente'): ?>
                                        <a href="solicitud_editar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger btn-eliminar" data-id="<?php echo $solicitud['id']; ?>" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Confirmación para Eliminar -->
<div class="modal fade" id="eliminarModal" tabindex="-1" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eliminarModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar esta solicitud? Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarEliminar" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Manejar botón de eliminar
        $('.btn-eliminar').click(function() {
            let id = $(this).data('id');
            $('#btnConfirmarEliminar').attr('href', 'solicitud_eliminar.php?id=' + id);
            $('#eliminarModal').modal('show');
        });
    });
</script>

<?php include 'footer.php'; ?>