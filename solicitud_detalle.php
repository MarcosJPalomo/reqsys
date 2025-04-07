<?php
// solicitud_detalle.php - Ver detalles de una solicitud específica
require_once 'config.php';
verificarSesion();

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de solicitud no válido');
    header('Location: mis_solicitudes.php');
    exit;
}

$db = conectarDB();
$solicitud_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// Determinar si el usuario tiene acceso a esta solicitud
$permitido = false;

if ($rol === 'solicitante') {
    // El solicitante solo puede ver sus propias solicitudes
    $query = "SELECT COUNT(*) as cuenta FROM solicitudes WHERE id = $solicitud_id AND usuario_id = $usuario_id";
    $resultado = $db->query($query);
    $row = $resultado->fetch_assoc();
    $permitido = ($row['cuenta'] > 0);
} else if (in_array($rol, ['almacen', 'operaciones', 'compras', 'abastecimiento'])) {
    // Otros roles pueden ver todas las solicitudes
    $permitido = true;
}

if (!$permitido) {
    mostrarAlerta('danger', 'No tiene permisos para ver esta solicitud');
    header('Location: index.php');
    exit;
}

// Obtener datos de la solicitud
$query = "SELECT s.*, u.nombre, u.apellido, u.departamento,
          CASE 
            WHEN s.estatus = 'pendiente' THEN 'Pendiente'
            WHEN s.estatus = 'procesada' THEN 'Procesada'
            WHEN s.estatus = 'rechazada' THEN 'Rechazada'
          END as estatus_texto
          FROM solicitudes s
          JOIN usuarios u ON s.usuario_id = u.id
          WHERE s.id = $solicitud_id";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Solicitud no encontrada');
    header('Location: index.php');
    exit;
}

$solicitud = $resultado->fetch_assoc();

// Obtener partidas de la solicitud
$query = "SELECT p.*, r.* FROM partidas_solicitud p
          LEFT JOIN requisitos_anexos r ON p.id = r.partida_id
          WHERE p.solicitud_id = $solicitud_id
          ORDER BY p.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[] = $row;
    }
}

// Verificar si la solicitud ha sido procesada en una requisición
$query = "SELECT r.id, r.folio FROM requisiciones r
          JOIN solicitudes_requisiciones sr ON r.id = sr.requisicion_id
          WHERE sr.solicitud_id = $solicitud_id";

$resultado = $db->query($query);
$requisicion = null;

if ($resultado && $resultado->num_rows > 0) {
    $requisicion = $resultado->fetch_assoc();
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Detalle de Solicitud #<?php echo $solicitud_id; ?></h1>
    
    <?php if ($rol === 'solicitante'): ?>
        <a href="mis_solicitudes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Mis Solicitudes
        </a>
    <?php elseif ($rol === 'almacen'): ?>
        <a href="solicitudes_pendientes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver a Solicitudes Pendientes
        </a>
    <?php else: ?>
        <a href="javascript:history.back()" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Información de la Solicitud</h6>
                
                <div>
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
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Solicitante:</label>
                        <p><?php echo htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido']); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Departamento:</label>
                        <p><?php echo htmlspecialchars($solicitud['departamento']); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Fecha de Solicitud:</label>
                        <p><?php echo formatearFecha($solicitud['fecha_solicitud']); ?></p>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Fecha Deseada:</label>
                        <p><?php echo formatearFecha($solicitud['deseado_para']); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Destinado a:</label>
                        <p><?php echo htmlspecialchars($solicitud['destinado_a']); ?></p>
                    </div>
                    <?php if ($requisicion): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Requisición:</label>
                        <p>
                            <a href="requisicion_detalle.php?id=<?php echo $requisicion['id']; ?>" class="btn btn-sm btn-outline-primary">
                                Ver Requisición #<?php echo $requisicion['id']; ?> (Folio: <?php echo $requisicion['folio']; ?>)
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
                        <th>Cantidad</th>
                        <th>Unidad</th>
                        <th>Descripción</th>
                        <th>Referencias</th>
                        <th>Comentarios</th>
                        <th>Requisitos Anexos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partidas as $partida): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($partida['partida']); ?></td>
                        <td><?php echo number_format($partida['cantidad'], 2); ?></td>
                        <td><?php echo htmlspecialchars($partida['unidad']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($partida['descripcion'])); ?></td>
                        <td><?php echo htmlspecialchars($partida['referencias']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($partida['comentarios'])); ?></td>
                        <td>
                            <?php
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
                                echo '<ul class="mb-0 ps-3">';
                                foreach ($requisitos as $req) {
                                    echo '<li>' . $req . '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '<span class="text-muted">Ninguno</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>