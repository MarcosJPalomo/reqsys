<?php
// requisiciones_cotizadas.php - Listado de requisiciones cotizadas para usuario de abastecimiento
require_once 'config.php';
verificarSesion();
verificarRol(['abastecimiento']);

$db = conectarDB();

// Obtener requisiciones que ya tienen cotizaciones finalizadas por compras
$query = "SELECT r.*, u.nombre, u.apellido, 
          (SELECT COUNT(*) FROM partidas_requisicion WHERE requisicion_id = r.id AND estatus != 'rechazada') as num_partidas,
          c.compras_finalizado, c.abastecimiento_aprobado, c.folio_orden_compra
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.estatus = 'en_proceso' 
          AND c.operaciones_aprobado = 1
          AND c.compras_finalizado = 1
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
    <h1 class="h3">Requisiciones Cotizadas</h1>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-gray-800">Listado de Requisiciones</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requisiciones)): ?>
            <div class="alert alert-info">
                No hay requisiciones cotizadas pendientes de autorización en este momento.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Folio</th>
                            <th>Fecha Requisición</th>
                            <th>Partidas</th>
                            <th>Estatus Autorización</th>
                            <th>Folio OC</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requisiciones as $requisicion): ?>
                            <tr>
                                <td><?php echo $requisicion['id']; ?></td>
                                <td><?php echo htmlspecialchars($requisicion['folio']); ?></td>
                                <td><?php echo formatearFecha($requisicion['fecha_requisicion']); ?></td>
                                <td><span class="badge bg-info"><?php echo $requisicion['num_partidas']; ?></span></td>
                                <td>
                                    <?php if ($requisicion['abastecimiento_aprobado']): ?>
                                        <span class="badge bg-success">Autorizada</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $requisicion['folio_orden_compra'] ? htmlspecialchars($requisicion['folio_orden_compra']) : '-'; ?></td>
                                <td>
                                    <a href="requisicion_detalle.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if (!$requisicion['abastecimiento_aprobado']): ?>
                                        <a href="autorizar_cotizacion.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Autorizar">
                                            <i class="bi bi-check-lg"></i> Autorizar
                                        </a>
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

<?php include 'footer.php'; ?>