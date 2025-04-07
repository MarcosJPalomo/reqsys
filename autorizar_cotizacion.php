<?php
// autorizar_cotizacion.php - Formulario para autorizar cotizaciones por abastecimiento
require_once 'config.php';
verificarSesion();
verificarRol(['abastecimiento']);

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Verificar que la requisición existe y está cotizada
$query = "SELECT r.*, u.nombre, u.apellido, c.compras_finalizado, c.abastecimiento_aprobado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id 
          AND r.estatus = 'en_proceso'
          AND c.compras_finalizado = 1";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada o no está cotizada');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Verificar que no esté ya autorizada
if ($requisicion['abastecimiento_aprobado']) {
    mostrarAlerta('warning', 'Esta requisición ya ha sido autorizada');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

// Obtener partidas aprobadas de la requisición
$query = "SELECT pr.*, ps.partida as partida_original, ps.descripcion, ps.unidad, 
          s.destinado_a
          FROM partidas_requisicion pr
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          WHERE pr.requisicion_id = $requisicion_id
          AND pr.estatus != 'rechazada'
          ORDER BY pr.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[$row['id']] = $row;
    }
}

// Obtener cotizaciones disponibles por partida
$query = "SELECT c.*, p.nombre as proveedor_nombre, p.contacto, p.telefono, p.email
          FROM cotizaciones c
          LEFT JOIN proveedores p ON c.proveedor_id = p.id
          WHERE c.partida_requisicion_id IN (" . implode(',', array_keys($partidas)) . ")
          ORDER BY c.partida_requisicion_id, c.precio_total";

$resultado = $db->query($query);
$cotizaciones = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        if (!isset($cotizaciones[$row['partida_requisicion_id']])) {
            $cotizaciones[$row['partida_requisicion_id']] = [];
        }
        $cotizaciones[$row['partida_requisicion_id']][] = $row;
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se enviaron selecciones de proveedores
    if (!isset($_POST['proveedor']) || !is_array($_POST['proveedor'])) {
        mostrarAlerta('danger', 'Debe seleccionar proveedores para todas las partidas');
    } else {
        $folio_oc = sanitizar($db, $_POST['folio_oc'] ?? '');
        
        // Iniciar transacción
        $db->begin_transaction();
        
        try {
            // Actualizar cada cotización seleccionada
            foreach ($_POST['proveedor'] as $partida_id => $cotizacion_id) {
                $cotizacion_id = (int)$cotizacion_id;
                $partida_id = (int)$partida_id;
                
                // Verificar que la cotización existe para esta partida
                $encontrado = false;
                foreach ($cotizaciones[$partida_id] ?? [] as $cot) {
                    if ($cot['id'] == $cotizacion_id) {
                        $encontrado = true;
                        break;
                    }
                }
                
                if (!$encontrado) {
                    throw new Exception("La cotización seleccionada no es válida para la partida $partida_id");
                }
                
                // Marcar la cotización como seleccionada
                $query = "UPDATE cotizaciones SET seleccionada = 0 WHERE partida_requisicion_id = $partida_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar cotizaciones: " . $db->error);
                }
                
                $query = "UPDATE cotizaciones SET seleccionada = 1 WHERE id = $cotizacion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al seleccionar cotización: " . $db->error);
                }
            }
            
            // Actualizar estatus de la requisición a finalizada
            $query = "UPDATE requisiciones SET estatus = 'finalizada' WHERE id = $requisicion_id";
            if (!$db->query($query)) {
                throw new Exception("Error al actualizar estatus de requisición: " . $db->error);
            }
            
            // Actualizar control de la requisición
            $query = "UPDATE control_requisicion SET 
                      abastecimiento_aprobado = 1,
                      folio_orden_compra = '$folio_oc',
                      fecha_autorizacion = NOW()
                      WHERE requisicion_id = $requisicion_id";
            
            if (!$db->query($query)) {
                throw new Exception("Error al actualizar control de requisición: " . $db->error);
            }
            
            // Confirmar transacción
            $db->commit();
            
            mostrarAlerta('success', 'Cotizaciones autorizadas correctamente. Folio OC: ' . $folio_oc);
            header('Location: requisiciones_cotizadas.php');
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
    <h1 class="h3">Autorizar Cotizaciones - Requisición #<?php echo $requisicion_id; ?> (Folio: <?php echo htmlspecialchars($requisicion['folio']); ?>)</h1>
    <a href="requisiciones_cotizadas.php" class="btn btn-secondary">
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

<form method="POST" id="formAutorizacion">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Folio de Orden de Compra</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="folio_oc" class="form-label">Folio de Orden de Compra <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folio_oc" name="folio_oc" required>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php foreach ($partidas as $partida_id => $partida): 
        // Verificar si hay cotizaciones para esta partida
        if (!isset($cotizaciones[$partida_id]) || empty($cotizaciones[$partida_id])) {
            continue;
        }
        
        // Obtener el color de la partida
        $color_class = '';
        if (!empty($partida['color'])) {
            $color_class = 'partida-' . $partida['color'];
        }
    ?>
        <div class="card shadow mb-4 <?php echo $color_class; ?>">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    Partida: <?php echo htmlspecialchars($partida['partida_original']); ?> - 
                    <?php echo htmlspecialchars(substr($partida['descripcion'], 0, 100)); ?>...
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($partida['descripcion'])); ?></p>
                        <p>
                            <strong>Cantidad aprobada:</strong> <?php echo number_format($partida['cantidad_aprobada'], 2); ?> 
                            <?php echo htmlspecialchars($partida['unidad']); ?>
                        </p>
                        <p><strong>Destinado a:</strong> <?php echo htmlspecialchars($partida['destinado_a']); ?></p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">Selección</th>
                                <th style="width: 20%">Proveedor</th>
                                <th style="width: 15%">Contacto</th>
                                <th style="width: 10%">Precio Unitario</th>
                                <th style="width: 10%">Precio Total</th>
                                <th style="width: 10%">Condiciones de Pago</th>
                                <th style="width: 10%">Tiempo de Entrega</th>
                                <th style="width: 10%">Moneda</th>
                                <th style="width: 10%">Cotizó</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cotizaciones[$partida_id] as $index => $cotizacion): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="proveedor[<?php echo $partida_id; ?>]" 
                                                   value="<?php echo $cotizacion['id']; ?>" 
                                                   id="prov_<?php echo $partida_id; ?>_<?php echo $cotizacion['id']; ?>"
                                                   <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($cotizacion['proveedor_nombre']); ?></td>
                                    <td>
                                        <?php if ($cotizacion['proveedor_id'] > 0 && isset($cotizacion['contacto'])): ?>
                                            <?php echo htmlspecialchars($cotizacion['contacto']); ?><br>
                                            <small><?php echo htmlspecialchars($cotizacion['telefono']); ?></small><br>
                                            <small><?php echo htmlspecialchars($cotizacion['email']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Proveedor sin registrar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($cotizacion['precio_unitario'], 2); ?></td>
                                    <td>$<?php echo number_format($cotizacion['precio_total'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['condiciones_pago']); ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['tiempo_entrega']); ?></td>
                                    <td><?php echo $cotizacion['moneda']; ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['cotizo']); ?></td>
                                </tr>
                                <?php if (!empty($cotizacion['instrucciones'])): ?>
                                <tr>
                                    <td colspan="9" class="bg-light">
                                        <strong>Instrucciones/Observaciones:</strong> <?php echo nl2br(htmlspecialchars($cotizacion['instrucciones'])); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="d-flex justify-content-end mb-4">
        <a href="requisiciones_cotizadas.php" class="btn btn-secondary me-2">Cancelar</a>
        <button type="submit" class="btn btn-success">Autorizar Cotizaciones</button>
    </div>
</form>

<?php include 'footer.php'; ?>
 Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Verificar que la requisición existe y está cotizada
$query = "SELECT r.*, u.nombre, u.apellido, c.compras_finalizado, c.abastecimiento_aprobado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id 
          AND r.estatus = 'en_proceso'
          AND c.compras_finalizado = 1";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada o no está cotizada');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Verificar que no esté ya autorizada
if ($requisicion['abastecimiento_aprobado']) {
    mostrarAlerta('warning', 'Esta requisición ya ha sido autorizada');
    header('Location: requisiciones_cotizadas.php');
    exit;
}

// Obtener partidas aprobadas de la requisición
$query = "SELECT pr.*, ps.partida as partida_original, ps.descripcion, ps.unidad, 
          s.destinado_a
          FROM partidas_requisicion pr
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          WHERE pr.requisicion_id = $requisicion_id
          AND pr.estatus != 'rechazada'
          ORDER BY pr.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[$row['id']] = $row;
    }
}

// Obtener cotizaciones disponibles por partida
$query = "SELECT c.*, p.nombre as proveedor_nombre, p.contacto, p.telefono, p.email
          FROM cotizaciones c
          JOIN proveedores p ON c.proveedor_id = p.id
          WHERE c.partida_requisicion_id IN (" . implode(',', array_keys($partidas)) . ")
          ORDER BY c.partida_requisicion_id, c.precio_total";

$resultado = $db->query($query);
$cotizaciones = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        if (!isset($cotizaciones[$row['partida_requisicion_id']])) {
            $cotizaciones[$row['partida_requisicion_id']] = [];
        }
        $cotizaciones[$row['partida_requisicion_id']][] = $row;
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se enviaron selecciones de proveedores
    if (!isset($_POST['proveedor']) || !is_array($_POST['proveedor'])) {
        mostrarAlerta('danger', 'Debe seleccionar proveedores para todas las partidas');
    } else {
        $folio_oc = sanitizar($db, $_POST['folio_oc'] ?? '');
        
        // Iniciar transacción
        $db->begin_transaction();
        
        try {
            // Actualizar cada cotización seleccionada
            foreach ($_POST['proveedor'] as $partida_id => $cotizacion_id) {
                $cotizacion_id = (int)$cotizacion_id;
                $partida_id = (int)$partida_id;
                
                // Verificar que la cotización existe para esta partida
                $encontrado = false;
                foreach ($cotizaciones[$partida_id] ?? [] as $cot) {
                    if ($cot['id'] == $cotizacion_id) {
                        $encontrado = true;
                        break;
                    }
                }
                
                if (!$encontrado) {
                    throw new Exception("La cotización seleccionada no es válida para la partida $partida_id");
                }
                
                // Marcar la cotización como seleccionada
                $query = "UPDATE cotizaciones SET seleccionada = 0 WHERE partida_requisicion_id = $partida_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al actualizar cotizaciones: " . $db->error);
                }
                
                $query = "UPDATE cotizaciones SET seleccionada = 1 WHERE id = $cotizacion_id";
                if (!$db->query($query)) {
                    throw new Exception("Error al seleccionar cotización: " . $db->error);
                }
            }
            
            // Actualizar estatus de la requisición a finalizada
            $query = "UPDATE requisiciones SET estatus = 'finalizada' WHERE id = $requisicion_id";
            if (!$db->query($query)) {
                throw new Exception("Error al actualizar estatus de requisición: " . $db->error);
            }
            
            // Actualizar control de la requisición
            $query = "UPDATE control_requisicion SET 
                      abastecimiento_aprobado = 1,
                      folio_orden_compra = '$folio_oc',
                      fecha_autorizacion = NOW()
                      WHERE requisicion_id = $requisicion_id";
            
            if (!$db->query($query)) {
                throw new Exception("Error al actualizar control de requisición: " . $db->error);
            }
            
            // Confirmar transacción
            $db->commit();
            
            mostrarAlerta('success', 'Cotizaciones autorizadas correctamente. Folio OC: ' . $folio_oc);
            header('Location: requisiciones_cotizadas.php');
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
    <h1 class="h3">Autorizar Cotizaciones - Requisición #<?php echo $requisicion_id; ?> (Folio: <?php echo htmlspecialchars($requisicion['folio']); ?>)</h1>
    <a href="requisiciones_cotizadas.php" class="btn btn-secondary">
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

<form method="POST" id="formAutorizacion">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Folio de Orden de Compra</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="folio_oc" class="form-label">Folio de Orden de Compra <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="folio_oc" name="folio_oc" required>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php foreach ($partidas as $partida_id => $partida): 
        // Verificar si hay cotizaciones para esta partida
        if (!isset($cotizaciones[$partida_id]) || empty($cotizaciones[$partida_id])) {
            continue;
        }
        
        // Obtener el color de la partida
        $color_class = '';
        if (!empty($partida['color'])) {
            $color_class = 'partida-' . $partida['color'];
        }
    ?>
        <div class="card shadow mb-4 <?php echo $color_class; ?>">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    Partida: <?php echo htmlspecialchars($partida['partida_original']); ?> - 
                    <?php echo htmlspecialchars(substr($partida['descripcion'], 0, 100)); ?>...
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($partida['descripcion'])); ?></p>
                        <p>
                            <strong>Cantidad aprobada:</strong> <?php echo number_format($partida['cantidad_aprobada'], 2); ?> 
                            <?php echo htmlspecialchars($partida['unidad']); ?>
                        </p>
                        <p><strong>Destinado a:</strong> <?php echo htmlspecialchars($partida['destinado_a']); ?></p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">Selección</th>
                                <th style="width: 20%">Proveedor</th>
                                <th style="width: 15%">Contacto</th>
                                <th style="width: 10%">Precio Unitario</th>
                                <th style="width: 10%">Precio Total</th>
                                <th style="width: 10%">Condiciones de Pago</th>
                                <th style="width: 10%">Tiempo de Entrega</th>
                                <th style="width: 10%">Moneda</th>
                                <th style="width: 10%">Cotizó</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cotizaciones[$partida_id] as $index => $cotizacion): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="proveedor[<?php echo $partida_id; ?>]" 
                                                   value="<?php echo $cotizacion['id']; ?>" 
                                                   id="prov_<?php echo $partida_id; ?>_<?php echo $cotizacion['id']; ?>"
                                                   <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($cotizacion['proveedor_nombre']); ?></td>
                                    <td>
                                        <?php if ($cotizacion['proveedor_id'] > 0 && isset($cotizacion['contacto'])): ?>
                                            <?php echo htmlspecialchars($cotizacion['contacto']); ?><br>
                                            <small><?php echo htmlspecialchars($cotizacion['telefono']); ?></small><br>
                                            <small><?php echo htmlspecialchars($cotizacion['email']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Proveedor sin registrar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($cotizacion['precio_unitario'], 2); ?></td>
                                    <td>$<?php echo number_format($cotizacion['precio_total'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['condiciones_pago']); ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['tiempo_entrega']); ?></td>
                                    <td><?php echo $cotizacion['moneda']; ?></td>
                                    <td><?php echo htmlspecialchars($cotizacion['cotizo']); ?></td>
                                </tr>
                                <?php if (!empty($cotizacion['instrucciones'])): ?>
                                <tr>
                                    <td colspan="9" class="bg-light">
                                        <strong>Instrucciones/Observaciones:</strong> <?php echo nl2br(htmlspecialchars($cotizacion['instrucciones'])); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="d-flex justify-content-end mb-4">
        <a href="requisiciones_cotizadas.php" class="btn btn-secondary me-2">Cancelar</a>
        <button type="submit" class="btn btn-success">Autorizar Cotizaciones</button>
    </div>
</form>

<?php include 'footer.php'; ?>