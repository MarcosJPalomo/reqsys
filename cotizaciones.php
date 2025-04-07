<?php
// cotizaciones.php - Listado de todas las cotizaciones para usuario de compras
require_once 'config.php';
verificarSesion();
verificarRol(['compras']);

$db = conectarDB();

// Obtener cotizaciones con información de requisiciones y partidas
$query = "SELECT c.*, 
          p.nombre as proveedor_nombre, 
          pr.requisicion_id,
          pr.partida_solicitud_id, 
          ps.descripcion as descripcion_partida,
          ps.partida as partida_original,
          r.folio as folio_requisicion,
          s.destinado_a
          FROM cotizaciones c
          JOIN proveedores p ON c.proveedor_id = p.id
          JOIN partidas_requisicion pr ON c.partida_requisicion_id = pr.id
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN requisiciones r ON pr.requisicion_id = r.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          ORDER BY c.fecha_cotizacion DESC";

$resultado = $db->query($query);
$cotizaciones = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        // Usar la requisición_id como clave para agrupar
        if (!isset($cotizaciones[$row['requisicion_id']])) {
            $cotizaciones[$row['requisicion_id']] = [];
        }
        $cotizaciones[$row['requisicion_id']][] = $row;
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Historial de Cotizaciones</h1>
</div>

<?php foreach ($cotizaciones as $requisicion_id => $grupo_cotizaciones): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-gray-800">
                Requisición #<?php echo $requisicion_id; ?> 
                (Folio: <?php echo htmlspecialchars($grupo_cotizaciones[0]['folio_requisicion']); ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Partida</th>
                            <th>Descripción</th>
                            <th>Destinado a</th>
                            <th>Proveedor</th>
                            <th>Precio Unitario</th>
                            <th>Precio Total</th>
                            <th>Moneda</th>
                            <th>Tiempo de Entrega</th>
                            <th>Cotizó</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupo_cotizaciones as $cotizacion): ?>
                            <tr <?php echo $cotizacion['seleccionada'] ? 'class="table-success"' : ''; ?>>
                                <td><?php echo htmlspecialchars($cotizacion['partida_original']); ?></td>
                                <td><?php echo htmlspecialchars(substr($cotizacion['descripcion_partida'], 0, 50)); ?>...</td>
                                <td><?php echo htmlspecialchars($cotizacion['destinado_a']); ?></td>
                                <td><?php echo htmlspecialchars($cotizacion['proveedor_nombre']); ?></td>
                                <td>$<?php echo number_format($cotizacion['precio_unitario'], 2); ?></td>
                                <td>$<?php echo number_format($cotizacion['precio_total'], 2); ?></td>
                                <td><?php echo htmlspecialchars($cotizacion['moneda']); ?></td>
                                <td><?php echo htmlspecialchars($cotizacion['tiempo_entrega']); ?></td>
                                <td><?php echo htmlspecialchars($cotizacion['cotizo']); ?></td>
                                <td><?php echo formatearFecha($cotizacion['fecha_cotizacion']); ?></td>
                                <td>
                                    <a href="requisicion_detalle.php?id=<?php echo $requisicion_id; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($cotizacion['seleccionada']): ?>
                                        <span class="badge bg-success ms-1">Seleccionada</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($cotizaciones)): ?>
    <div class="alert alert-info">
        No hay cotizaciones registradas en este momento.
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>