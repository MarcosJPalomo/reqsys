<?php
// requisicion_nueva.php - Formulario para crear una nueva requisición
require_once 'config.php';
verificarSesion();
verificarRol(['almacen']);

// Verificar que haya solicitudes seleccionadas
if (!isset($_SESSION['solicitudes_seleccionadas']) || empty($_SESSION['solicitudes_seleccionadas'])) {
    mostrarAlerta('danger', 'No hay solicitudes seleccionadas para generar requisición');
    header('Location: solicitudes_pendientes.php');
    exit;
}

$db = conectarDB();
$usuario_id = (int)$_SESSION['usuario_id'];

// Obtener IDs de solicitudes seleccionadas
$solicitudes_ids = $_SESSION['solicitudes_seleccionadas'];
$solicitudes_ids_str = implode(',', array_map('intval', $solicitudes_ids));

// Obtener información de las solicitudes seleccionadas
$query = "SELECT s.*, u.nombre, u.apellido, u.departamento 
          FROM solicitudes s
          JOIN usuarios u ON s.usuario_id = u.id
          WHERE s.id IN ($solicitudes_ids_str) AND s.estatus = 'pendiente'";

$resultado = $db->query($query);
$solicitudes = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $solicitudes[$row['id']] = $row;
    }
}

// Si alguna solicitud ya no está disponible, regresar
if (count($solicitudes) !== count($solicitudes_ids)) {
    mostrarAlerta('warning', 'Algunas solicitudes seleccionadas ya no están disponibles');
    unset($_SESSION['solicitudes_seleccionadas']);
    header('Location: solicitudes_pendientes.php');
    exit;
}

// Obtener todas las partidas de las solicitudes
$query = "SELECT p.*, s.destinado_a, r.*
          FROM partidas_solicitud p
          JOIN solicitudes s ON p.solicitud_id = s.id
          LEFT JOIN requisitos_anexos r ON p.id = r.partida_id
          WHERE p.solicitud_id IN ($solicitudes_ids_str)
          ORDER BY p.solicitud_id, p.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[] = $row;
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar datos del formulario
    $folio = sanitizar($db, $_POST['folio']);
    $fecha_requisicion = sanitizar($db, $_POST['fecha_requisicion']);
    
    // Verificar que el folio no exista ya
    $query = "SELECT id FROM requisiciones WHERE folio = '$folio'";
    $resultado = $db->query($query);
    
    if ($resultado && $resultado->num_rows > 0) {
        mostrarAlerta('danger', 'El folio ya existe. Por favor, use otro.');
    } else {
        // Iniciar transacción
        $db->begin_transaction();
        
        try {
            // Insertar requisición
            $query = "INSERT INTO requisiciones (usuario_almacen_id, folio, fecha_requisicion) 
                      VALUES ($usuario_id, '$folio', '$fecha_requisicion')";
            
            if (!$db->query($query)) {
                throw new Exception("Error al crear la requisición: " . $db->error);
            }
            
            $requisicion_id = $db->insert_id;
            
            // Registrar en control_requisicion
            $query = "INSERT INTO control_requisicion (requisicion_id) VALUES ($requisicion_id)";
            if (!$db->query($query)) {
                throw new Exception("Error al crear el control de requisición: " . $db->error);
            }
            
            // Relacionar las solicitudes con la requisición
            foreach ($solicitudes_ids as $solicitud_id) {
                $query = "INSERT INTO solicitudes_requisiciones (solicitud_id, requisicion_id) 
                          VALUES ($solicitud_id, $requisicion_id)";
                
                if (!$db->query($query)) {
                    throw new Exception("Error al relacionar solicitud con requisición: " . $db->error);
                }
                
                // Actualizar estatus de la solicitud a 'procesada'
                $query = "UPDATE solicitudes SET estatus = 'procesada' WHERE id = $solicitud_id";
                
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar estatus de solicitud: " . $db->error);
                }
            }
            
            // Procesar partidas
            foreach ($_POST['partida_id'] as $index => $partida_id) {
                $partida_id = (int)$partida_id;
                $clave_microsip = sanitizar($db, $_POST['clave_microsip'][$index]);
                $existencia = (float)$_POST['existencia'][$index];
                $cantidad = (float)$_POST['cantidad'][$index];
                
                // Insertar partida de requisición
                $query = "INSERT INTO partidas_requisicion (requisicion_id, partida_solicitud_id, clave_microsip, 
                          existencia_almacen, cantidad_solicitada) 
                          VALUES ($requisicion_id, $partida_id, '$clave_microsip', $existencia, $cantidad)";
                
                if (!$db->query($query)) {
                    throw new Exception("Error al insertar partida de requisición: " . $db->error);
                }
            }
            
            // Confirmar transacción
            $db->commit();
            
            // Limpiar selección de sesión
            unset($_SESSION['solicitudes_seleccionadas']);
            
            mostrarAlerta('success', 'Requisición creada exitosamente con folio: ' . $folio);
            header('Location: requisiciones.php');
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
    <h1 class="h3">Generar Nueva Requisición</h1>
    <a href="solicitudes_pendientes.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Solicitudes
    </a>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Información de la Requisición</h6>
    </div>
    <div class="card-body">
        <form id="requisicionForm" method="POST">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="folio" class="form-label">Folio <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folio" name="folio" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="fecha_requisicion" class="form-label">Fecha de Requisición <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_requisicion" name="fecha_requisicion" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="num_solicitudes" class="form-label">Solicitudes Fusionadas</label>
                        <input type="text" class="form-control" id="num_solicitudes" value="<?php echo count($solicitudes); ?>" readonly>
                    </div>
                </div>
            </div>
            
            <h6 class="text-primary mb-3">Solicitudes Incluidas</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Departamento</th>
                            <th>Destinado a</th>
                            <th>Fecha Solicitud</th>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <h6 class="text-primary mb-3">Partidas Incluidas</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>Solicitud</th>
                            <th>Partida</th>
                            <th>Destinado a</th>
                            <th>Descripción</th>
                            <th>Clave Microsip</th>
                            <th>Existencia</th>
                            <th>Cantidad Original</th>
                            <th>Cantidad a Solicitar</th>
                            <th>Unidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidas as $index => $partida): ?>
                            <tr>
                                <td><?php echo $partida['solicitud_id']; ?></td>
                                <td><?php echo htmlspecialchars($partida['partida']); ?></td>
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
                                    
                                    <?php if (!empty($partida['comentarios'])): ?>
                                        <div class="mt-1 small text-muted">
                                            <strong>Comentarios: </strong><?php echo htmlspecialchars($partida['comentarios']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="hidden" name="partida_id[]" value="<?php echo $partida['id']; ?>">
                                    <input type="text" class="form-control form-control-sm" name="clave_microsip[]" placeholder="Clave">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm existencia" name="existencia[]" placeholder="0.00">
                                </td>
                                <td class="text-center">
                                    <?php echo number_format($partida['cantidad'], 2); ?>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm cantidad" name="cantidad[]" value="<?php echo $partida['cantidad']; ?>" required>
                                </td>
                                <td><?php echo htmlspecialchars($partida['unidad']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="solicitudes_pendientes.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Generar Requisición</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Actualizar cantidad a solicitar según existencia
        $('.existencia').on('input', function() {
            let row = $(this).closest('tr');
            let existencia = parseFloat($(this).val()) || 0;
            let cantidadOriginal = parseFloat(row.find('td:eq(6)').text().replace(',', '')) || 0;
            
            // Sugerir la cantidad a solicitar (si hay existencia, restar de lo solicitado)
            let cantidadSugerida = Math.max(0, cantidadOriginal - existencia);
            row.find('.cantidad').val(cantidadSugerida.toFixed(2));
        });
    });
</script>

<?php include 'footer.php'; ?>