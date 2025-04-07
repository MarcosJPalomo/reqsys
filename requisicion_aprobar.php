<?php
// requisicion_aprobar.php - Formulario para aprobar/rechazar requisición por operaciones
require_once 'config.php';
verificarSesion();
verificarRol(['operaciones']);

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: requisiciones_pendientes.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Verificar que la requisición existe y está pendiente
$query = "SELECT r.*, u.nombre, u.apellido, c.operaciones_aprobado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          LEFT JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id AND r.estatus = 'pendiente'";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada o no está pendiente de aprobación');
    header('Location: requisiciones_pendientes.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Verificar que no esté ya aprobada por operaciones
if ($requisicion['operaciones_aprobado']) {
    mostrarAlerta('warning', 'Esta requisición ya ha sido aprobada por operaciones');
    header('Location: requisiciones_pendientes.php');
    exit;
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

// Procesar formulario de aprobación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar tipo de acción
    if (!isset($_POST['accion']) || !in_array($_POST['accion'], ['aprobar', 'rechazar'])) {
        mostrarAlerta('danger', 'Acción no válida');
    } else {
        $accion = $_POST['accion'];
        
        // Iniciar transacción
        $db->begin_transaction();
        
        try {
            if ($accion === 'aprobar') {
                // Actualizar estatus de requisición
                $estatus_final = 'aprobada'; // Por defecto, asumimos que se aprueba todo
                $alguna_rechazada = false;
                $todas_rechazadas = true;
                
                // Procesar cada partida
                foreach ($_POST['partida_id'] as $index => $partida_id) {
                    $partida_id = (int)$partida_id;
                    $cantidad_aprobada = (float)$_POST['cantidad_aprobada'][$index];
                    $estatus_partida = $_POST['estatus_partida'][$index];
                    
                    // Actualizar partida de requisición
                    $query = "UPDATE partidas_requisicion 
                              SET cantidad_aprobada = $cantidad_aprobada, 
                                  estatus = '$estatus_partida'
                              WHERE id = $partida_id AND requisicion_id = $requisicion_id";
                    
                    if (!$db->query($query)) {
                        throw new Exception("Error al actualizar partida: " . $db->error);
                    }
                    
                    // Marcar si hay alguna partida rechazada o aprobada
                    if ($estatus_partida === 'rechazada') {
                        $alguna_rechazada = true;
                    }
                    if ($estatus_partida !== 'rechazada') {
                        $todas_rechazadas = false;
                    }
                }
                
                // Determinar estatus general
                if ($todas_rechazadas) {
                    $estatus_final = 'rechazada';
                } else if ($alguna_rechazada) {
                    $estatus_final = 'parcial';
                }
                
                // Actualizar requisición
                $query = "UPDATE requisiciones SET estatus = '$estatus_final' WHERE id = $requisicion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar requisición: " . $db->error);
                }
                
                // Marcar como aprobada por operaciones
                $query = "UPDATE control_requisicion SET operaciones_aprobado = 1 WHERE requisicion_id = $requisicion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar control de requisición: " . $db->error);
                }
                
                $mensaje = 'Requisición aprobada exitosamente';
                if ($estatus_final === 'parcial') {
                    $mensaje = 'Requisición aprobada parcialmente';
                }
                
                mostrarAlerta('success', $mensaje);
                
            } else { // Rechazar toda la requisición
                // Actualizar todas las partidas como rechazadas
                $query = "UPDATE partidas_requisicion SET estatus = 'rechazada' WHERE requisicion_id = $requisicion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar partidas: " . $db->error);
                }
                
                // Actualizar requisición
                $query = "UPDATE requisiciones SET estatus = 'rechazada' WHERE id = $requisicion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar requisición: " . $db->error);
                }
                
                // Marcar como aprobada por operaciones (para que no aparezca como pendiente)
                $query = "UPDATE control_requisicion SET operaciones_aprobado = 1 WHERE requisicion_id = $requisicion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar control de requisición: " . $db->error);
                }
                
                mostrarAlerta('warning', 'Requisición rechazada completamente');
            }
            
            // Confirmar transacción
            $db->commit();
            header('Location: requisiciones_pendientes.php');
            exit;
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            $db->rollback();
            mostrarAlerta('danger', $e->getMessage());
        }
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Aprobar Requisición #<?php echo $requisicion_id; ?> (Folio: <?php echo htmlspecialchars($requisicion['folio']); ?>)</h1>
    <a href="requisiciones_pendientes.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Información de la Requisición</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Creada por:</label>
                <p><?php echo htmlspecialchars($requisicion['nombre'] . ' ' . $requisicion['apellido']); ?></p>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Fecha de Requisición:</label>
                <p><?php echo formatearFecha($requisicion['fecha_requisicion']); ?></p>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Fecha de Creación:</label>
                <p><?php echo formatearFecha($requisicion['fecha_creacion']); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="formAprobacion">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Partidas a Aprobar</h6>
            <div>
                <button type="button" class="btn btn-outline-success btn-sm me-2" id="btnAprobarTodas">Aprobar todas</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnRechazarTodas">Rechazar todas</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Partida</th>
                            <th>Destinado a</th>
                            <th>Descripción</th>
                            <th>Clave Microsip</th>
                            <th>Existencia</th>
                            <th>Cantidad Original</th>
                            <th>Cantidad Solicitada</th>
                            <th>Cantidad a Aprobar</th>
                            <th>Unidad</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidas as $index => $partida): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($partida['partida_original']); ?></td>
                                <td><?php echo htmlspecialchars($partida['destinado_a']); ?></td>
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
                                <td><?php echo htmlspecialchars($partida['clave_microsip']); ?></td>
                                <td><?php echo number_format($partida['existencia_almacen'], 2); ?></td>
                                <td><?php echo number_format($partida['cantidad_solicitada'], 2); ?></td>
                                <td><?php echo number_format($partida['cantidad_solicitada'], 2); ?></td>
                                <td>
                                    <input type="hidden" name="partida_id[]" value="<?php echo $partida['id']; ?>">
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm cantidad-aprobada" 
                                          name="cantidad_aprobada[]" value="<?php echo $partida['cantidad_solicitada']; ?>" required>
                                </td>
                                <td><?php echo htmlspecialchars($partida['unidad']); ?></td>
                                <td>
                                    <select class="form-select form-select-sm estatus-partida" name="estatus_partida[]">
                                        <option value="aprobada">Aprobar</option>
                                        <option value="rechazada">Rechazar</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-danger me-2" id="btnRechazar">Rechazar Requisición</button>
                <button type="submit" class="btn btn-success" id="btnAprobar">Aprobar Requisición</button>
            </div>
            
            <input type="hidden" name="accion" id="accion" value="aprobar">
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Manejar botones de aprobación/rechazo general
        $('#btnAprobar').click(function() {
            $('#accion').val('aprobar');
            $('#formAprobacion').submit();
        });
        
        $('#btnRechazar').click(function() {
            if (confirm('¿Está seguro que desea rechazar toda la requisición?')) {
                $('#accion').val('rechazar');
                $('#formAprobacion').submit();
            }
        });
        
        // Manejar botones de aprobar/rechazar todas las partidas
        $('#btnAprobarTodas').click(function() {
            $('.estatus-partida').val('aprobada');
            
            // Restablecer las cantidades a las originales
            $('.estatus-partida').each(function() {
                let row = $(this).closest('tr');
                let cantidadOriginal = parseFloat(row.find('td:eq(6)').text().replace(',', '')) || 0;
                row.find('.cantidad-aprobada').val(cantidadOriginal.toFixed(2));
                row.find('.cantidad-aprobada').prop('readonly', false);
            });
        });
        
        $('#btnRechazarTodas').click(function() {
            $('.estatus-partida').val('rechazada');
            
            // Establecer cantidades a 0 y hacer readonly
            $('.cantidad-aprobada').val('0.00');
            $('.cantidad-aprobada').prop('readonly', true);
        });
        
        // Manejar cambio de estatus de partida
        $('.estatus-partida').change(function() {
            let row = $(this).closest('tr');
            let cantidadInput = row.find('.cantidad-aprobada');
            
            if ($(this).val() === 'rechazada') {
                cantidadInput.val('0.00');
                cantidadInput.prop('readonly', true);
            } else {
                let cantidadOriginal = parseFloat(row.find('td:eq(6)').text().replace(',', '')) || 0;
                cantidadInput.val(cantidadOriginal.toFixed(2));
                cantidadInput.prop('readonly', false);
            }
        });
    });
</script>

<?php include 'footer.php'; ?>