<?php
// requisiciones_pendientes.php - Listado de requisiciones por aprobar para usuario de operaciones
require_once 'config.php';
verificarSesion();
verificarRol(['operaciones']);

$db = conectarDB();

// Obtener requisiciones pendientes de aprobación
$query = "SELECT r.*, u.nombre, u.apellido, 
          (SELECT COUNT(*) FROM partidas_requisicion WHERE requisicion_id = r.id) as num_partidas
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.estatus = 'pendiente' AND (c.operaciones_aprobado = 0 OR c.operaciones_aprobado IS NULL)
          ORDER BY r.fecha_creacion ASC";

$resultado = $db->query($query);
$requisiciones = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $requisiciones[] = $row;
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Requisiciones Pendientes de Aprobación</h1>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-gray-800">Listado de Requisiciones</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requisiciones)): ?>
            <div class="alert alert-info">
                No hay requisiciones pendientes de aprobación en este momento.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Folio</th>
                            <th>Fecha Requisición</th>
                            <th>Creado por</th>
                            <th>Fecha Creación</th>
                            <th>Partidas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requisiciones as $requisicion): ?>
                            <tr>
                                <td><?php echo $requisicion['id']; ?></td>
                                <td><?php echo htmlspecialchars($requisicion['folio']); ?></td>
                                <td><?php echo formatearFecha($requisicion['fecha_requisicion']); ?></td>
                                <td><?php echo htmlspecialchars($requisicion['nombre'] . ' ' . $requisicion['apellido']); ?></td>
                                <td><?php echo formatearFecha($requisicion['fecha_creacion']); ?></td>
                                <td><span class="badge bg-info"><?php echo $requisicion['num_partidas']; ?></span></td>
                                <td>
                                    <a href="requisicion_detalle.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="requisicion_aprobar.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Aprobar">
                                        <i class="bi bi-check-lg"></i> Aprobar
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

<?php include 'footer.php'; ?>