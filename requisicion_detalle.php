<?php
// requisicion_detalle.php - Ver detalles de una requisición específica
require_once 'config.php';
verificarSesion();

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: index.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// Obtener datos de la requisición
$query = "SELECT r.*, u.nombre, u.apellido, 
          CASE 
            WHEN r.estatus = 'pendiente' THEN 'Pendiente de aprobación'
            WHEN r.estatus = 'aprobada' THEN 'Aprobada'
            WHEN r.estatus = 'parcial' THEN 'Aprobada parcialmente'
            WHEN r.estatus = 'rechazada' THEN 'Rechazada'
            WHEN r.estatus = 'en_proceso' THEN 'En proceso de compra'
            WHEN r.estatus = 'finalizada' THEN 'Finalizada'
          END as estatus_texto,
          c.operaciones_aprobado, c.compras_finalizado, c.abastecimiento_aprobado, c.folio_orden_compra
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada');
    header('Location: index.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Obtener solicitudes asociadas a esta requisición
$query = "SELECT s.*, u.nombre, u.apellido, u.departamento
          FROM solicitudes s
          JOIN solicitudes_requisiciones sr ON s.id = sr.solicitud_id
          JOIN usuarios u ON s.usuario_id = u.id
          WHERE sr.requisicion_id = $requisicion_id
          ORDER BY s.id";

$resultado = $db->query($query);
$solicitudes = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}

// Obtener partidas de la requisición
$query = "SELECT pr.*, ps.partida as partida_original, ps.descripcion, ps.unidad, ps.referencias, 
          ps.comentarios, s.destinado_a, ra.*
          FROM partidas_requisicion pr
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          LEFT JOIN requisitos_anexos ra ON ps.id = ra.partida_id
          WHERE pr.requisicion_id = $requisicion_id
          ORDER BY pr.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[] = $row;
    }
}

// Obtener cotizaciones seleccionadas si ya está finalizada
$cotizaciones_seleccionadas = [];

if ($requisicion['abastecimiento_aprobado']) {
    $query = "SELECT c.*, p.nombre as proveedor_nombre, pr.id as partida_id
              FROM cotizaciones c
              JOIN proveedores p ON c.proveedor_id = p.id
              JOIN partidas_requisicion pr ON c.partida_requisicion_id = pr.id
              WHERE pr.requisicion_id = $requisicion_id AND c.seleccionada = 1
              ORDER BY pr.id";
    
    $resultado = $db->query($query);
    
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $cotizaciones_seleccionadas[$row['partida_id']] = $row;
        }
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Detalle de Requisición #<?php echo $requisicion_id; ?></h1>
    
    <div>
        <?php if (in_array($rol, ['compras', 'abastecimiento']) && ($requisicion['compras_finalizado'] || $requisicion['abastecimiento_aprobado'])): ?>
            <a href="exportar_excel.php?id=<?php echo $requisicion_id; ?>" class="btn btn-success me-2">
                <i class="bi bi-file-excel"></i> Exportar a Excel
            </a>
        <?php endif; ?>
        
        <?php if ($rol === 'almacen'): ?>
            <a href="requisiciones.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Requisiciones
            </a>
        <?php elseif ($rol === 'operaciones'): ?>
            <a href="requisiciones_pendientes.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Pendientes
            </a>
        <?php elseif ($rol === 'compras'): ?>
            <a href="requisiciones_por_cotizar.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Por Cotizar
            </a>
        <?php elseif ($rol === 'abastecimiento'): ?>
            <a href="requisiciones_cotizadas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Cotizadas
            </a>
        <?php else: ?>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Información de la Requisición</h6>
                
                <div>
                    <?php 
                    $class = '';
                    switch ($requisicion['estatus']) {
                        case 'pendiente':
                            $class = 'bg-warning';
                            break;
                        case 'aprobada':
                        case 'finalizada':
                            $class = 'bg-success';
                            break;
                        case 'parcial':
                            $class = 'bg-info';
                            break;
                        case 'rechazada':
                            $class = 'bg-danger';
                            break;
                        case 'en_proceso':
                            $class = 'bg-primary';
                            break;
                    }
                    ?>
                    <span class="badge <?php echo $class; ?>"><?php echo $requisicion['estatus_texto']; ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Folio:</label>
                        <p><?php echo htmlspecialchars($requisicion['folio']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Creada por:</label>
                        <p><?php echo htmlspecialchars($requisicion['nombre'] . ' ' . $requisicion['apellido']); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Requisición:</label>
                        <p><?php echo formatearFecha($requisicion['fecha_requisicion']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Creación:</label>
                        <p><?php echo formatearFecha($requisicion['fecha_creacion']); ?></p>
                    </div>
                </div>
                <?php if ($requisicion['folio_orden_compra']): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Folio de Orden de Compra:</label>
                        <p><?php echo htmlspecialchars($requisicion['folio_orden_compra']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Estado del Proceso</h6>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Creación de Requisición
                        <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Aprobación Operaciones
                        <?php if ($requisicion['operaciones_aprobado']): ?>
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i></span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill"><i class="bi bi-hourglass-split"></i></span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Cotización Compras
                        <?php if ($requisicion['compras_finalizado']): ?>
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i></span>
                        <?php elseif ($requisicion['operaciones_aprobado']): ?>
                            <span class="badge bg-warning rounded-pill"><i class="bi bi-hourglass-split"></i></span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill"><i class="bi bi-hourglass-split"></i></span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Autorización Abastecimiento
                        <?php if ($requisicion['abastecimiento_aprobado']): ?>
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-lg"></i></span>
                        <?php elseif ($requisicion['compras_finalizado']): ?>
                            <span class="badge bg-warning rounded-pill"><i class="bi bi-hourglass-split"></i></span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill"><i class="bi bi-hourglass-split"></i></span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Solicitudes Incluidas</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Solicitante</th>
                        <th>Departamento</th>
                        <th>Destinado a</th>
                        <th>Fecha Solicitud</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td><?php echo $solicitud['id']; ?></td>
                            <td><?php echo htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['departamento']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['destinado_a']); ?></td>
                            <td><?php echo formatearFecha($solicitud['fecha_solicitud']); ?></td>
                            <td>
                                <a href="solicitud_detalle.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Partidas</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Partida</th>
                        <th>Clave Microsip</th>
                        <th>Descripción</th>
                        <th>Destinado a</th>
                        <th>Existencia</th>
                        <th>Cantidad Solicitada</th>
                        <?php if ($requisicion['operaciones_aprobado']): ?>
                            <th>Cantidad Aprobada</th>
                        <?php endif; ?>
                        <th>Unidad</th>
                        <th>Estatus</th>
                        <?php if ($requisicion['abastecimiento_aprobado']): ?>
                            <th>Proveedor</th>
                            <th>Precio</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partidas as $partida): 
                        $color_class = '';
                        if (!empty($partida['color'])) {
                            $color_class = 'partida-' . $partida['color'];
                        }
                    ?>
                        <tr class="<?php echo $color_class; ?>">
                            <td><?php echo htmlspecialchars($partida['partida_original']); ?></td>
                            <td><?php echo htmlspecialchars($partida['clave_microsip']); ?></td>
                            <td>
                                <?php echo nl2br(htmlspecialchars($partida['descripcion'])); ?>
                                
                                <?php
                                // Mostrar requisitos anexos si existen
                                $requisitos = [];
                                if ($partida['certificado_seguridad']) $requisitos[] = "Certificado de seguridad";
                                if ($partida['garantia']) $requisitos[] = "Garantía";
                                if ($partida['manual_operacion']) $requisitos[] = "Manual de operación";
                                if ($partida['asesoria']) $requisitos[] = "Asesoría";
                                if ($partida['boleta_bascula']) $requisitos[] = "Boleta de báscula";
                                if ($partida['carta_compromiso']) $requisitos[] = "Carta compromiso";
                                if ($partida['ficha_tecnica']) $requisitos[] = "Ficha técnica";
                                if ($partida['certificado_diploma']) $requisitos[] = "Certificado o diploma";
                                if ($partida['otro']) {
                                    $requisitos[] = "Otro: " . htmlspecialchars($partida['otro_descripcion']);
                                }
                                
                                if (!empty($requisitos)) {
                                    echo '<div class="mt-2 small text-muted">';
                                    echo '<strong>Requisitos: </strong>';
                                    echo implode(', ', $requisitos);
                                    echo '</div>';
                                }
                                ?>
                                
                                <?php if (!empty($partida['referencias'])): ?>
                                    <div class="mt-1 small text-muted">
                                        <strong>Referencias: </strong><?php echo htmlspecialchars($partida['referencias']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($partida['destinado_a']); ?></td>
                            <td><?php echo number_format($partida['existencia_almacen'], 2); ?></td>
                            <td><?php echo number_format($partida['cantidad_solicitada'], 2); ?></td>
                            <?php if ($requisicion['operaciones_aprobado']): ?>
                                <td><?php echo number_format($partida['cantidad_aprobada'], 2); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($partida['unidad']); ?></td>
                            <td>
                                <?php 
                                $estatus_class = '';
                                $estatus_text = '';
                                
                                switch ($partida['estatus']) {
                                    case 'pendiente':
                                        $estatus_class = 'bg-warning';
                                        $estatus_text = 'Pendiente';
                                        break;
                                    case 'aprobada':
                                        $estatus_class = 'bg-success';
                                        $estatus_text = 'Aprobada';
                                        break;
                                    case 'rechazada':
                                        $estatus_class = 'bg-danger';
                                        $estatus_text = 'Rechazada';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $estatus_class; ?>"><?php echo $estatus_text; ?></span>
                            </td>
                            <?php if ($requisicion['abastecimiento_aprobado']): ?>
                                <td>
                                    <?php if (isset($cotizaciones_seleccionadas[$partida['id']])): ?>
                                        <?php echo htmlspecialchars($cotizaciones_seleccionadas[$partida['id']]['proveedor_nombre']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($cotizaciones_seleccionadas[$partida['id']])): ?>
                                        <?php 
                                            $cot = $cotizaciones_seleccionadas[$partida['id']];
                                            echo '$' . number_format($cot['precio_unitario'], 2) . ' / $' . number_format($cot['precio_total'], 2) . ' ' . $cot['moneda'];
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>