<?php
// requisiciones.php - Listado de requisiciones para usuario de almacén
require_once 'config.php';
verificarSesion();
verificarRol(['almacen']);

$db = conectarDB();

// Obtener todas las requisiciones
$query = "SELECT r.*, u.nombre, u.apellido, 
          (SELECT COUNT(*) FROM partidas_requisicion WHERE requisicion_id = r.id) as num_partidas,
          c.operaciones_aprobado, c.compras_finalizado, c.abastecimiento_aprobado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
          ORDER BY r.fecha_creacion DESC";

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
    <h1 class="h3">Requisiciones</h1>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-gray-800">Listado de Requisiciones</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requisiciones)): ?>
            <div class="alert alert-info">
                No hay requisiciones registradas. Genere una nueva requisición desde la sección de solicitudes pendientes.
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
                            <th>Estatus</th>
                            <th>Progreso</th>
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
                                    <?php 
                                    $class = '';
                                    $status_text = '';
                                    
                                    switch ($requisicion['estatus']) {
                                        case 'pendiente':
                                            $class = 'bg-warning';
                                            $status_text = 'Pendiente de aprobación';
                                            break;
                                        case 'aprobada':
                                            $class = 'bg-success';
                                            $status_text = 'Aprobada';
                                            break;
                                        case 'parcial':
                                            $class = 'bg-info';
                                            $status_text = 'Aprobada parcialmente';
                                            break;
                                        case 'rechazada':
                                            $class = 'bg-danger';
                                            $status_text = 'Rechazada';
                                            break;
                                        case 'en_proceso':
                                            $class = 'bg-primary';
                                            $status_text = 'En proceso de compra';
                                            break;
                                        case 'finalizada':
                                            $class = 'bg-success';
                                            $status_text = 'Finalizada';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <?php
                                        $progress = 0;
                                        $progress_class = "bg-secondary";
                                        $progress_text = "En espera";
                                        
                                        if ($requisicion['operaciones_aprobado']) {
                                            $progress = 33;
                                            $progress_class = "bg-info";
                                            $progress_text = "Operaciones";
                                            
                                            if ($requisicion['compras_finalizado']) {
                                                $progress = 66;
                                                $progress_class = "bg-primary";
                                                $progress_text = "Compras";
                                                
                                                if ($requisicion['abastecimiento_aprobado']) {
                                                    $progress = 100;
                                                    $progress_class = "bg-success";
                                                    $progress_text = "Completado";
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $progress; ?>%;" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $progress_text; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="requisicion_detalle.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($requisicion['estatus'] === 'pendiente'): ?>
                                        <a href="requisicion_editar.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Editar">
                                            <i class="bi bi-pencil"></i>
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