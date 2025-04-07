<?php
// index.php - Dashboard principal del sistema
require_once 'config.php';
verificarSesion();

$db = conectarDB();
$usuario_id = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// Estadísticas generales según el rol
$stats = [
    'pendientes' => 0,
    'en_proceso' => 0,
    'finalizadas' => 0,
    'recientes' => []
];

if ($rol === 'solicitante') {
    // Contar solicitudes pendientes, procesadas, rechazadas
    $query = "SELECT 
              SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
              SUM(CASE WHEN estatus = 'procesada' THEN 1 ELSE 0 END) as procesadas,
              SUM(CASE WHEN estatus = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
              FROM solicitudes 
              WHERE usuario_id = $usuario_id";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['pendientes'] = $row['pendientes'];
        $stats['en_proceso'] = $row['procesadas'];
        $stats['finalizadas'] = $row['rechazadas'];
    }
    
    // Obtener solicitudes recientes
    $query = "SELECT id, destinado_a, fecha_solicitud, estatus
              FROM solicitudes 
              WHERE usuario_id = $usuario_id
              ORDER BY fecha_solicitud DESC
              LIMIT 5";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $stats['recientes'][] = $row;
        }
    }
} elseif ($rol === 'almacen') {
    // Contar solicitudes pendientes
    $query = "SELECT COUNT(*) as pendientes
              FROM solicitudes 
              WHERE estatus = 'pendiente'";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['pendientes'] = $row['pendientes'];
    }
    
    // Contar requisiciones en proceso y finalizadas
    $query = "SELECT 
              SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) as en_proceso,
              SUM(CASE WHEN estatus IN ('aprobada', 'parcial', 'en_proceso', 'finalizada') THEN 1 ELSE 0 END) as finalizadas
              FROM requisiciones";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['en_proceso'] = $row['en_proceso'];
        $stats['finalizadas'] = $row['finalizadas'];
    }
    
    // Obtener requisiciones recientes
    $query = "SELECT r.id, r.folio, r.fecha_requisicion, r.estatus
              FROM requisiciones r
              ORDER BY r.fecha_creacion DESC
              LIMIT 5";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $stats['recientes'][] = $row;
        }
    }
} elseif ($rol === 'operaciones') {
    // Contar requisiciones pendientes de aprobación
    $query = "SELECT COUNT(*) as pendientes
              FROM requisiciones r
              LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE r.estatus = 'pendiente' AND (c.operaciones_aprobado = 0 OR c.operaciones_aprobado IS NULL)";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['pendientes'] = $row['pendientes'];
    }
    
    // Contar requisiciones aprobadas y rechazadas
    $query = "SELECT 
              SUM(CASE WHEN r.estatus IN ('aprobada', 'parcial', 'en_proceso', 'finalizada') THEN 1 ELSE 0 END) as aprobadas,
              SUM(CASE WHEN r.estatus = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
              FROM requisiciones r
              LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE c.operaciones_aprobado = 1";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['en_proceso'] = $row['aprobadas'];
        $stats['finalizadas'] = $row['rechazadas'];
    }
    
    // Obtener requisiciones recientes
    $query = "SELECT r.id, r.folio, r.fecha_requisicion, r.estatus
              FROM requisiciones r
              LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE c.operaciones_aprobado = 1
              ORDER BY r.fecha_creacion DESC
              LIMIT 5";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $stats['recientes'][] = $row;
        }
    }
} elseif ($rol === 'compras') {
    // Contar requisiciones pendientes de cotización
    $query = "SELECT COUNT(*) as pendientes
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE (r.estatus = 'aprobada' OR r.estatus = 'parcial' OR r.estatus = 'en_proceso') 
              AND c.operaciones_aprobado = 1
              AND c.compras_finalizado = 0";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['pendientes'] = $row['pendientes'];
    }
    
    // Contar requisiciones con cotización finalizada
    $query = "SELECT COUNT(*) as finalizadas
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE c.compras_finalizado = 1";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['finalizadas'] = $row['finalizadas'];
    }
    
    // Obtener requisiciones recientes
    $query = "SELECT r.id, r.folio, r.fecha_requisicion, r.estatus, c.compras_finalizado
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE (r.estatus = 'aprobada' OR r.estatus = 'parcial' OR r.estatus = 'en_proceso')
              AND c.operaciones_aprobado = 1
              ORDER BY r.fecha_creacion DESC
              LIMIT 5";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $stats['recientes'][] = $row;
        }
    }
} elseif ($rol === 'abastecimiento') {
    // Contar requisiciones pendientes de autorización
    $query = "SELECT COUNT(*) as pendientes
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE r.estatus = 'en_proceso' 
              AND c.compras_finalizado = 1
              AND c.abastecimiento_aprobado = 0";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['pendientes'] = $row['pendientes'];
    }
    
    // Contar requisiciones autorizadas
    $query = "SELECT COUNT(*) as autorizadas
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE c.abastecimiento_aprobado = 1";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $stats['finalizadas'] = $row['autorizadas'];
    }
    
    // Obtener requisiciones recientes
    $query = "SELECT r.id, r.folio, r.fecha_requisicion, r.estatus, c.folio_orden_compra
              FROM requisiciones r
              JOIN control_requisicion c ON r.id = c.requisicion_id
              WHERE c.compras_finalizado = 1
              ORDER BY c.fecha_autorizacion DESC
              LIMIT 5";
    
    $resultado = $db->query($query);
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $stats['recientes'][] = $row;
        }
    }
}

// Obtener las requisiciones más recientes para todos los usuarios
$query = "SELECT r.id, r.folio, r.fecha_requisicion, r.estatus,
          u.nombre, u.apellido, c.folio_orden_compra,
          CASE 
            WHEN r.estatus = 'pendiente' THEN 'Pendiente de aprobación'
            WHEN r.estatus = 'aprobada' THEN 'Aprobada'
            WHEN r.estatus = 'parcial' THEN 'Aprobada parcialmente'
            WHEN r.estatus = 'rechazada' THEN 'Rechazada'
            WHEN r.estatus = 'en_proceso' THEN 'En proceso de compra'
            WHEN r.estatus = 'finalizada' THEN 'Finalizada'
          END as estatus_texto
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
          ORDER BY r.fecha_creacion DESC
          LIMIT 10";

$resultado = $db->query($query);
$requisiciones_recientes = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $requisiciones_recientes[] = $row;
    }
}

$db->close();

include 'header.php';
?>

<h1 class="h3 mb-4">Dashboard</h1>

<div class="row">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php 
                            switch ($rol) {
                                case 'solicitante':
                                    echo 'Solicitudes Pendientes';
                                    break;
                                case 'almacen':
                                    echo 'Solicitudes por Procesar';
                                    break;
                                case 'operaciones':
                                    echo 'Requisiciones por Aprobar';
                                    break;
                                case 'compras':
                                    echo 'Requisiciones por Cotizar';
                                    break;
                                case 'abastecimiento':
                                    echo 'Requisiciones por Autorizar';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pendientes']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clipboard-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php 
                            switch ($rol) {
                                case 'solicitante':
                                    echo 'Solicitudes Procesadas';
                                    break;
                                case 'almacen':
                                    echo 'Requisiciones en Proceso';
                                    break;
                                case 'operaciones':
                                    echo 'Requisiciones Aprobadas';
                                    break;
                                case 'compras':
                                    echo 'Requisiciones en Proceso';
                                    break;
                                case 'abastecimiento':
                                    echo 'Requisiciones en Proceso';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['en_proceso']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?php 
                            switch ($rol) {
                                case 'solicitante':
                                    echo 'Solicitudes Rechazadas';
                                    break;
                                case 'almacen':
                                    echo 'Requisiciones Finalizadas';
                                    break;
                                case 'operaciones':
                                    echo 'Requisiciones Rechazadas';
                                    break;
                                case 'compras':
                                    echo 'Requisiciones Finalizadas';
                                    break;
                                case 'abastecimiento':
                                    echo 'Requisiciones Autorizadas';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['finalizadas']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php 
                    switch ($rol) {
                        case 'solicitante':
                            echo 'Mis Solicitudes Recientes';
                            break;
                        case 'almacen':
                            echo 'Requisiciones Recientes';
                            break;
                        case 'operaciones':
                            echo 'Requisiciones Recientemente Aprobadas';
                            break;
                        case 'compras':
                            echo 'Requisiciones Recientes por Cotizar';
                            break;
                        case 'abastecimiento':
                            echo 'Requisiciones Recientes para Autorizar';
                            break;
                    }
                    ?>
                </h6>
                
                <?php if ($rol === 'solicitante'): ?>
                    <a href="solicitud_nueva.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Nueva Solicitud
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($stats['recientes'])): ?>
                    <div class="text-center">
                        <p class="text-muted">No hay registros recientes</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($stats['recientes'] as $reciente): ?>
                            <?php if ($rol === 'solicitante'): ?>
                                <a href="solicitud_detalle.php?id=<?php echo $reciente['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Solicitud #<?php echo $reciente['id']; ?></h6>
                                        <small><?php echo formatearFecha($reciente['fecha_solicitud']); ?></small>
                                    </div>
                                    <p class="mb-1">Destinado a: <?php echo htmlspecialchars($reciente['destinado_a']); ?></p>
                                    <small>
                                        <?php 
                                        $class = '';
                                        $estatus_text = '';
                                        
                                        switch ($reciente['estatus']) {
                                            case 'pendiente':
                                                $class = 'text-warning';
                                                $estatus_text = 'Pendiente';
                                                break;
                                            case 'procesada':
                                                $class = 'text-success';
                                                $estatus_text = 'Procesada';
                                                break;
                                            case 'rechazada':
                                                $class = 'text-danger';
                                                $estatus_text = 'Rechazada';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $class; ?>">Estatus: <?php echo $estatus_text; ?></span>
                                    </small>
                                </a>
                            <?php else: ?>
                                <a href="requisicion_detalle.php?id=<?php echo $reciente['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Requisición #<?php echo $reciente['id']; ?> - Folio: <?php echo htmlspecialchars($reciente['folio']); ?></h6>
                                        <small><?php echo formatearFecha($reciente['fecha_requisicion']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php 
                                        $class = '';
                                        $estatus_text = '';
                                        
                                        switch ($reciente['estatus']) {
                                            case 'pendiente':
                                                $class = 'text-warning';
                                                $estatus_text = 'Pendiente de aprobación';
                                                break;
                                            case 'aprobada':
                                                $class = 'text-success';
                                                $estatus_text = 'Aprobada';
                                                break;
                                            case 'parcial':
                                                $class = 'text-info';
                                                $estatus_text = 'Aprobada parcialmente';
                                                break;
                                            case 'rechazada':
                                                $class = 'text-danger';
                                                $estatus_text = 'Rechazada';
                                                break;
                                            case 'en_proceso':
                                                $class = 'text-primary';
                                                $estatus_text = 'En proceso de compra';
                                                break;
                                            case 'finalizada':
                                                $class = 'text-success';
                                                $estatus_text = 'Finalizada';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $class; ?>">Estatus: <?php echo $estatus_text; ?></span>
                                        
                                        <?php if ($rol === 'abastecimiento' && isset($reciente['folio_orden_compra']) && !empty($reciente['folio_orden_compra'])): ?>
                                            <br><small>OC: <?php echo htmlspecialchars($reciente['folio_orden_compra']); ?></small>
                                        <?php endif; ?>
                                        
                                        <?php if ($rol === 'compras' && isset($reciente['compras_finalizado'])): ?>
                                            <br><small>Cotización: <?php echo $reciente['compras_finalizado'] ? 'Finalizada' : 'En proceso'; ?></small>
                                        <?php endif; ?>
                                    </p>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Últimas Requisiciones del Sistema</h6>
            </div>
            <div class="card-body">
                <?php if (empty($requisiciones_recientes)): ?>
                    <div class="text-center">
                        <p class="text-muted">No hay requisiciones registradas</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Creado por</th>
                                    <th>Estatus</th>
                                    <th>OC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requisiciones_recientes as $req): ?>
                                    <tr>
                                        <td><a href="requisicion_detalle.php?id=<?php echo $req['id']; ?>"><?php echo $req['id']; ?></a></td>
                                        <td><?php echo htmlspecialchars($req['folio']); ?></td>
                                        <td><?php echo formatearFecha($req['fecha_requisicion']); ?></td>
                                        <td><?php echo htmlspecialchars($req['nombre'] . ' ' . $req['apellido']); ?></td>
                                        <td>
                                            <?php 
                                            $class = '';
                                            switch ($req['estatus']) {
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
                                            <span class="badge <?php echo $class; ?>"><?php echo $req['estatus_texto']; ?></span>
                                        </td>
                                        <td><?php echo $req['folio_orden_compra'] ? htmlspecialchars($req['folio_orden_compra']) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>