<?php
// cotizacion.php - Gestión de cotizaciones para una requisición
require_once 'config.php';
verificarSesion();
verificarRol(['compras']);

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: requisiciones_por_cotizar.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Verificar que la requisición existe y está aprobada
$query = "SELECT r.*, u.nombre, u.apellido, c.compras_finalizado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id 
          AND (r.estatus = 'aprobada' OR r.estatus = 'parcial' OR r.estatus = 'en_proceso')
          AND c.operaciones_aprobado = 1";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada o no está aprobada');
    header('Location: requisiciones_por_cotizar.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Obtener partidas aprobadas de la requisición
$query = "SELECT pr.*, ps.partida as partida_original, ps.descripcion, ps.unidad, 
          s.destinado_a, ra.*
          FROM partidas_requisicion pr
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          LEFT JOIN requisitos_anexos ra ON ps.id = ra.partida_id
          WHERE pr.requisicion_id = $requisicion_id
          AND (pr.estatus = 'aprobada' OR pr.estatus = 'pendiente')
          ORDER BY pr.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[$row['id']] = $row;
    }
}

// Obtener proveedores
$query = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
$resultado = $db->query($query);
$proveedores = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $proveedores[$row['id']] = $row;
    }
}

// Obtener cotizaciones existentes
$query = "SELECT c.*, IFNULL(p.nombre, c.proveedor_nombre) as proveedor_nombre
          FROM cotizaciones c
          LEFT JOIN proveedores p ON c.proveedor_id = p.id
          WHERE c.partida_requisicion_id IN (" . implode(',', array_keys($partidas)) . ")
          ORDER BY c.partida_requisicion_id, c.id";

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

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determinar la acción a realizar
    if (isset($_POST['agregar_cotizacion'])) {
        // Agregar nueva cotización
        $partida_id = (int)$_POST['partida_id'];
        $proveedor_tipo = sanitizar($db, $_POST['proveedor_tipo'] ?? 'existente');
        $precio_unitario = (float)$_POST['precio_unitario'];
        $precio_total = (float)$_POST['precio_total'];
        $condiciones_pago = sanitizar($db, $_POST['condiciones_pago'] ?? '');
        $tiempo_entrega = sanitizar($db, $_POST['tiempo_entrega'] ?? '');
        $cotizo = sanitizar($db, $_POST['cotizo'] ?? '');
        $moneda = sanitizar($db, $_POST['moneda'] ?? 'MXN');
        $instrucciones = sanitizar($db, $_POST['instrucciones'] ?? '');
        
        // Validar datos
        if ($partida_id <= 0 || $precio_unitario <= 0) {
            mostrarAlerta('danger', 'Datos de cotización inválidos');
        } else {
            // Determinar proveedor_id y proveedor_nombre
            $proveedor_id = 0;
            $proveedor_nombre = '';
            
            if ($proveedor_tipo === 'existente') {
                $proveedor_id = (int)$_POST['proveedor_id'];
                if ($proveedor_id <= 0) {
                    mostrarAlerta('danger', 'Debe seleccionar un proveedor');
                    header("Location: cotizacion.php?id=$requisicion_id");
                    exit;
                }
                
                // Obtener nombre del proveedor
                $query = "SELECT nombre FROM proveedores WHERE id = $proveedor_id";
                $resultado = $db->query($query);
                if ($resultado && $resultado->num_rows > 0) {
                    $proveedor_nombre = $resultado->fetch_assoc()['nombre'];
                }
            } else {
                $proveedor_nombre = sanitizar($db, $_POST['proveedor_nombre'] ?? '');
                if (empty($proveedor_nombre)) {
                    mostrarAlerta('danger', 'Debe ingresar un nombre de proveedor');
                    header("Location: cotizacion.php?id=$requisicion_id");
                    exit;
                }
            }
            
            // Verificar si ya existe una cotización para esta partida y proveedor
            if ($proveedor_id > 0) {
                $query = "SELECT id FROM cotizaciones 
                          WHERE partida_requisicion_id = $partida_id AND proveedor_id = $proveedor_id";
            } else {
                $query = "SELECT id FROM cotizaciones 
                          WHERE partida_requisicion_id = $partida_id AND proveedor_nombre = '$proveedor_nombre'";
            }
            
            $resultado = $db->query($query);
            
            if ($resultado && $resultado->num_rows > 0) {
                // Actualizar cotización existente
                $cotizacion_id = $resultado->fetch_assoc()['id'];
                $query = "UPDATE cotizaciones SET 
                          precio_unitario = $precio_unitario,
                          precio_total = $precio_total,
                          condiciones_pago = '$condiciones_pago',
                          tiempo_entrega = '$tiempo_entrega',
                          cotizo = '$cotizo',
                          moneda = '$moneda',
                          instrucciones = '$instrucciones'
                          WHERE id = $cotizacion_id";
                
                if ($db->query($query)) {
                    mostrarAlerta('success', 'Cotización actualizada correctamente');
                } else {
                    mostrarAlerta('danger', 'Error al actualizar cotización: ' . $db->error);
                }
            } else {
                // Insertar nueva cotización
                $query = "INSERT INTO cotizaciones (partida_requisicion_id, proveedor_id, proveedor_nombre, precio_unitario, precio_total,
                          condiciones_pago, tiempo_entrega, cotizo, moneda, instrucciones)
                          VALUES ($partida_id, $proveedor_id, '$proveedor_nombre', $precio_unitario, $precio_total,
                          '$condiciones_pago', '$tiempo_entrega', '$cotizo', '$moneda', '$instrucciones')";
                
                if ($db->query($query)) {
                    mostrarAlerta('success', 'Cotización agregada correctamente');
                } else {
                    mostrarAlerta('danger', 'Error al agregar cotización: ' . $db->error);
                }
            }
        }
    } elseif (isset($_POST['eliminar_cotizacion'])) {
        // Eliminar cotización
        $cotizacion_id = (int)$_POST['cotizacion_id'];
        
        $query = "DELETE FROM cotizaciones WHERE id = $cotizacion_id";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Cotización eliminada correctamente');
        } else {
            mostrarAlerta('danger', 'Error al eliminar cotización: ' . $db->error);
        }
    } elseif (isset($_POST['cambiar_color'])) {
        // Cambiar color de partida
        $partida_id = (int)$_POST['partida_id'];
        $color = sanitizar($db, $_POST['color']);
        
        $query = "UPDATE partidas_requisicion SET color = '$color' WHERE id = $partida_id";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Color de partida actualizado');
        } else {
            mostrarAlerta('danger', 'Error al actualizar color: ' . $db->error);
        }
    } elseif (isset($_POST['finalizar_cotizacion'])) {
        // Finalizar proceso de cotización
        $finalizar = (int)$_POST['finalizar'];
        
        // Actualizar estatus en requisición
        $estatus = $finalizar ? 'en_proceso' : 'aprobada';
        $query = "UPDATE requisiciones SET estatus = '$estatus' WHERE id = $requisicion_id";
        
        if (!$db->query($query)) {
            mostrarAlerta('danger', 'Error al actualizar estatus de requisición: ' . $db->error);
        } else {
            // Actualizar flag de compras finalizado
            $query = "UPDATE control_requisicion SET compras_finalizado = $finalizar WHERE requisicion_id = $requisicion_id";
            
            if ($db->query($query)) {
                $mensaje = $finalizar ? 'Cotización finalizada correctamente' : 'Cotización reabierta para edición';
                mostrarAlerta('success', $mensaje);
            } else {
                mostrarAlerta('danger', 'Error al actualizar estado de cotización: ' . $db->error);
            }
        }
    }
    
    // Recargar la página para mostrar los cambios
    header("Location: cotizacion.php?id=$requisicion_id");
    exit;
}

$db->close();

include 'header.php';
?><?php
// cotizacion.php - Gestión de cotizaciones para una requisición
require_once 'config.php';
verificarSesion();
verificarRol(['compras']);

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: requisiciones_por_cotizar.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];

// Verificar que la requisición existe y está aprobada
$query = "SELECT r.*, u.nombre, u.apellido, c.compras_finalizado
          FROM requisiciones r
          JOIN usuarios u ON r.usuario_almacen_id = u.id
          JOIN control_requisicion c ON r.id = c.requisicion_id
          WHERE r.id = $requisicion_id 
          AND (r.estatus = 'aprobada' OR r.estatus = 'parcial' OR r.estatus = 'en_proceso')
          AND c.operaciones_aprobado = 1";

$resultado = $db->query($query);

if (!$resultado || $resultado->num_rows === 0) {
    mostrarAlerta('danger', 'Requisición no encontrada o no está aprobada');
    header('Location: requisiciones_por_cotizar.php');
    exit;
}

$requisicion = $resultado->fetch_assoc();

// Obtener partidas aprobadas de la requisición
$query = "SELECT pr.*, ps.partida as partida_original, ps.descripcion, ps.unidad, 
          s.destinado_a, ra.*
          FROM partidas_requisicion pr
          JOIN partidas_solicitud ps ON pr.partida_solicitud_id = ps.id
          JOIN solicitudes s ON ps.solicitud_id = s.id
          LEFT JOIN requisitos_anexos ra ON ps.id = ra.partida_id
          WHERE pr.requisicion_id = $requisicion_id
          AND (pr.estatus = 'aprobada' OR pr.estatus = 'pendiente')
          ORDER BY pr.id";

$resultado = $db->query($query);
$partidas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $partidas[$row['id']] = $row;
    }
}

// Obtener proveedores
$query = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
$resultado = $db->query($query);
$proveedores = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $proveedores[$row['id']] = $row;
    }
}

// Obtener cotizaciones existentes
$query = "SELECT c.*, p.nombre as proveedor_nombre
          FROM cotizaciones c
          JOIN proveedores p ON c.proveedor_id = p.id
          WHERE c.partida_requisicion_id IN (" . implode(',', array_keys($partidas)) . ")
          ORDER BY c.partida_requisicion_id, c.id";

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

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determinar la acción a realizar
    if (isset($_POST['agregar_cotizacion'])) {
        // Agregar nueva cotización
        $partida_id = (int)$_POST['partida_id'];
        $proveedor_id = (int)$_POST['proveedor_id'];
        $precio_unitario = (float)$_POST['precio_unitario'];
        $precio_total = (float)$_POST['precio_total'];
        $condiciones_pago = sanitizar($db, $_POST['condiciones_pago'] ?? '');
        $tiempo_entrega = sanitizar($db, $_POST['tiempo_entrega'] ?? '');
        $cotizo = sanitizar($db, $_POST['cotizo'] ?? '');
        $moneda = sanitizar($db, $_POST['moneda'] ?? 'MXN');
        $instrucciones = sanitizar($db, $_POST['instrucciones'] ?? '');
        
        // Validar datos
        if ($partida_id <= 0 || $proveedor_id <= 0 || $precio_unitario <= 0) {
            mostrarAlerta('danger', 'Datos de cotización inválidos');
        } else {
            // Verificar si ya existe una cotización para esta partida y proveedor
            $query = "SELECT id FROM cotizaciones 
                      WHERE partida_requisicion_id = $partida_id AND proveedor_id = $proveedor_id";
            $resultado = $db->query($query);
            
            if ($resultado && $resultado->num_rows > 0) {
                // Actualizar cotización existente
                $cotizacion_id = $resultado->fetch_assoc()['id'];
                $query = "UPDATE cotizaciones SET 
                          precio_unitario = $precio_unitario,
                          precio_total = $precio_total,
                          condiciones_pago = '$condiciones_pago',
                          tiempo_entrega = '$tiempo_entrega',
                          cotizo = '$cotizo',
                          moneda = '$moneda',
                          instrucciones = '$instrucciones'
                          WHERE id = $cotizacion_id";
                
                if ($db->query($query)) {
                    mostrarAlerta('success', 'Cotización actualizada correctamente');
                } else {
                    mostrarAlerta('danger', 'Error al actualizar cotización: ' . $db->error);
                }
            } else {
                // Insertar nueva cotización
                $query = "INSERT INTO cotizaciones (partida_requisicion_id, proveedor_id, precio_unitario, precio_total,
                          condiciones_pago, tiempo_entrega, cotizo, moneda, instrucciones)
                          VALUES ($partida_id, $proveedor_id, $precio_unitario, $precio_total,
                          '$condiciones_pago', '$tiempo_entrega', '$cotizo', '$moneda', '$instrucciones')";
                
                if ($db->query($query)) {
                    mostrarAlerta('success', 'Cotización agregada correctamente');
                } else {
                    mostrarAlerta('danger', 'Error al agregar cotización: ' . $db->error);
                }
            }
        }
    } elseif (isset($_POST['eliminar_cotizacion'])) {
        // Eliminar cotización
        $cotizacion_id = (int)$_POST['cotizacion_id'];
        
        $query = "DELETE FROM cotizaciones WHERE id = $cotizacion_id";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Cotización eliminada correctamente');
        } else {
            mostrarAlerta('danger', 'Error al eliminar cotización: ' . $db->error);
        }
    } elseif (isset($_POST['cambiar_color'])) {
        // Cambiar color de partida
        $partida_id = (int)$_POST['partida_id'];
        $color = sanitizar($db, $_POST['color']);
        
        $query = "UPDATE partidas_requisicion SET color = '$color' WHERE id = $partida_id";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Color de partida actualizado');
        } else {
            mostrarAlerta('danger', 'Error al actualizar color: ' . $db->error);
        }
    } elseif (isset($_POST['finalizar_cotizacion'])) {
        // Finalizar proceso de cotización
        $finalizar = (int)$_POST['finalizar'];
        
        // Actualizar estatus en requisición
        $estatus = $finalizar ? 'en_proceso' : 'aprobada';
        $query = "UPDATE requisiciones SET estatus = '$estatus' WHERE id = $requisicion_id";
        
        if (!$db->query($query)) {
            mostrarAlerta('danger', 'Error al actualizar estatus de requisición: ' . $db->error);
        } else {
            // Actualizar flag de compras finalizado
            $query = "UPDATE control_requisicion SET compras_finalizado = $finalizar WHERE requisicion_id = $requisicion_id";
            
            if ($db->query($query)) {
                $mensaje = $finalizar ? 'Cotización finalizada correctamente' : 'Cotización reabierta para edición';
                mostrarAlerta('success', $mensaje);
            } else {
                mostrarAlerta('danger', 'Error al actualizar estado de cotización: ' . $db->error);
            }
        }
    }
    
    // Recargar la página para mostrar los cambios
    header("Location: cotizacion.php?id=$requisicion_id");
    exit;
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Cotización de Requisición #<?php echo $requisicion_id; ?> (Folio: <?php echo htmlspecialchars($requisicion['folio']); ?>)</h1>
    <div>
        <a href="requisiciones_por_cotizar.php" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <form method="POST" class="d-inline">
            <input type="hidden" name="finalizar_cotizacion" value="1">
            <?php if ($requisicion['compras_finalizado']): ?>
                <input type="hidden" name="finalizar" value="0">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-unlock"></i> Reabrir para Edición
                </button>
            <?php else: ?>
                <input type="hidden" name="finalizar" value="1">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Finalizar Cotización
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-gray-800">Partidas a Cotizar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">Color</th>
                                <th style="width: 8%">Partida</th>
                                <th style="width: 12%">Destinado a</th>
                                <th style="width: 30%">Descripción</th>
                                <th style="width: 10%">Cantidad Aprobada</th>
                                <th style="width: 5%">Unidad</th>
                                <th style="width: 15%">Cotizaciones</th>
                                <th style="width: 15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidas as $partida_id => $partida): 
                                $color_class = '';
                                if (!empty($partida['color'])) {
                                    $color_class = 'partida-' . $partida['color'];
                                }
                            ?>
                                <tr class="<?php echo $color_class; ?>">
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="cambiar_color" value="1">
                                            <input type="hidden" name="partida_id" value="<?php echo $partida_id; ?>">
                                            <select class="form-select form-select-sm color-select" name="color" <?php echo $requisicion['compras_finalizado'] ? 'disabled' : ''; ?>>
                                                <option value="">Sin color</option>
                                                <option value="verde" <?php echo $partida['color'] === 'verde' ? 'selected' : ''; ?>>Verde</option>
                                                <option value="amarillo" <?php echo $partida['color'] === 'amarillo' ? 'selected' : ''; ?>>Amarillo</option>
                                                <option value="rojo" <?php echo $partida['color'] === 'rojo' ? 'selected' : ''; ?>>Rojo</option>
                                                <option value="azul" <?php echo $partida['color'] === 'azul' ? 'selected' : ''; ?>>Azul</option>
                                                <option value="gris" <?php echo $partida['color'] === 'gris' ? 'selected' : ''; ?>>Gris</option>
                                            </select>
                                        </form>
                                    </td>
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
                                    </td>
                                    <td><?php echo number_format($partida['cantidad_aprobada'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($partida['unidad']); ?></td>
                                    <td>
                                        <?php if (isset($cotizaciones[$partida_id])): ?>
                                            <div class="badge bg-success mb-1"><?php echo count($cotizaciones[$partida_id]); ?> cotizaciones</div>
                                            <div class="small">
                                                <?php foreach ($cotizaciones[$partida_id] as $cotizacion): ?>
                                                    <div class="mb-1">
                                                        <strong><?php echo htmlspecialchars($cotizacion['proveedor_nombre']); ?></strong>
                                                        <div>$ <?php echo number_format($cotizacion['precio_total'], 2); ?> <?php echo $cotizacion['moneda']; ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sin cotizaciones</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary btn-cotizar" 
                                                data-bs-toggle="modal" data-bs-target="#cotizacionModal"
                                                data-partida-id="<?php echo $partida_id; ?>"
                                                data-cantidad="<?php echo $partida['cantidad_aprobada']; ?>"
                                                data-descripcion="<?php echo htmlspecialchars($partida['descripcion']); ?>"
                                                data-unidad="<?php echo htmlspecialchars($partida['unidad']); ?>"
                                                <?php echo $requisicion['compras_finalizado'] ? 'disabled' : ''; ?>>
                                            <i class="bi bi-plus-circle"></i> Cotizar
                                        </button>
                                        
                                        <?php if (isset($cotizaciones[$partida_id])): ?>
                                            <button type="button" class="btn btn-sm btn-info btn-ver-cotizaciones"
                                                    data-bs-toggle="modal" data-bs-target="#verCotizacionesModal"
                                                    data-partida-id="<?php echo $partida_id; ?>">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar Cotización -->
<div class="modal fade" id="cotizacionModal" tabindex="-1" aria-labelledby="cotizacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cotizacionModalLabel">Agregar Cotización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formCotizacion" method="POST">
                    <input type="hidden" name="agregar_cotizacion" value="1">
                    <input type="hidden" name="partida_id" id="partida_id" value="">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="proveedor_tipo" class="form-label">Tipo de Proveedor <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="proveedor_tipo" id="tipo_existente" value="existente" checked>
                                <label class="form-check-label" for="tipo_existente">
                                    Proveedor Existente
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="proveedor_tipo" id="tipo_nuevo" value="nuevo">
                                <label class="form-check-label" for="tipo_nuevo">
                                    Proveedor Sin Registrar
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="moneda" class="form-label">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda">
                                <option value="MXN">Peso Mexicano (MXN)</option>
                                <option value="USD">Dólar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="proveedor_existente_container">
                        <div class="col-md-12">
                            <label for="proveedor_id" class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" id="proveedor_id" name="proveedor_id">
                                <option value="">Seleccione un proveedor</option>
                                <?php foreach ($proveedores as $id => $proveedor): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($proveedor['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="proveedor_nuevo_container" style="display: none;">
                        <div class="col-md-12">
                            <label for="proveedor_nombre" class="form-label">Nombre del Proveedor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="proveedor_nombre" name="proveedor_nombre">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <strong>Información de la Partida</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Descripción:</strong> <span id="descripcion-partida"></span></p>
                                    <p><strong>Cantidad:</strong> <span id="cantidad-partida"></span> <span id="unidad-partida"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="precio_unitario" class="form-label">Precio Unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="precio_unitario" name="precio_unitario" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="precio_total" class="form-label">Precio Total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="precio_total" name="precio_total" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="condiciones_pago" class="form-label">Condiciones de Pago</label>
                            <input type="text" class="form-control" id="condiciones_pago" name="condiciones_pago">
                        </div>
                        <div class="col-md-6">
                            <label for="tiempo_entrega" class="form-label">Tiempo de Entrega</label>
                            <input type="text" class="form-control" id="tiempo_entrega" name="tiempo_entrega">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cotizo" class="form-label">Cotizó</label>
                            <input type="text" class="form-control" id="cotizo" name="cotizo">
                        </div>
                        <div class="col-md-6">
                            <label for="instrucciones" class="form-label">Instrucciones/Observaciones</label>
                            <textarea class="form-control" id="instrucciones" name="instrucciones" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarCotizacion">Guardar Cotización</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Ver Cotizaciones -->
<div class="modal fade" id="verCotizacionesModal" tabindex="-1" aria-labelledby="verCotizacionesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verCotizacionesModalLabel">Cotizaciones de la Partida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="tablaCotizaciones">
                        <thead class="table-light">
                            <tr>
                                <th>Proveedor</th>
                                <th>Precio Unitario</th>
                                <th>Precio Total</th>
                                <th>Moneda</th>
                                <th>Condiciones de Pago</th>
                                <th>Tiempo de Entrega</th>
                                <th>Cotizó</th>
                                <th>Instrucciones</th>
                                <th>Fecha Cotización</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Se llena dinámicamente con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Variables para almacenar las cotizaciones
        let cotizaciones = <?php echo json_encode($cotizaciones); ?>;
        let partidas = <?php echo json_encode($partidas); ?>;
        let proveedores = <?php echo json_encode($proveedores); ?>;
        let cotizacionActual = null;
        
        // Actualizar precio total basado en precio unitario
        $('#precio_unitario').on('input', function() {
            let precioUnitario = parseFloat($(this).val()) || 0;
            let cantidad = parseFloat($('#cantidad-partida').text()) || 0;
            let precioTotal = precioUnitario * cantidad;
            $('#precio_total').val(precioTotal.toFixed(2));
        });
        
        // Al hacer click en botón de cotizar, llenar el modal
        $('.btn-cotizar').click(function() {
            let partidaId = $(this).data('partida-id');
            let cantidad = $(this).data('cantidad');
            let descripcion = $(this).data('descripcion');
            let unidad = $(this).data('unidad');
            
            $('#partida_id').val(partidaId);
            $('#descripcion-partida').text(descripcion);
            $('#cantidad-partida').text(cantidad);
            $('#unidad-partida').text(unidad);
            
            // Limpiar formulario
            $('#proveedor_id').val('');
            $('#moneda').val('MXN');
            $('#precio_unitario').val('');
            $('#precio_total').val('');
            $('#condiciones_pago').val('');
            $('#tiempo_entrega').val('');
            $('#cotizo').val('');
            $('#instrucciones').val('');
        });
        
        // Al hacer click en botón de ver cotizaciones
        $('.btn-ver-cotizaciones').click(function() {
            let partidaId = $(this).data('partida-id');
            let cotizacionesPartida = cotizaciones[partidaId] || [];
            
            // Limpiar tabla
            $('#tablaCotizaciones tbody').empty();
            
            // Llenar tabla con cotizaciones
            cotizacionesPartida.forEach(function(cot) {
                let row = `
                    <tr>
                        <td>${cot.proveedor_nombre}</td>
                        <td>${parseFloat(cot.precio_unitario).toFixed(2)}</td>
                        <td>${parseFloat(cot.precio_total).toFixed(2)}</td>
                        <td>${cot.moneda}</td>
                        <td>${cot.condiciones_pago || ''}</td>
                        <td>${cot.tiempo_entrega || ''}</td>
                        <td>${cot.cotizo || ''}</td>
                        <td>${cot.instrucciones || ''}</td>
                        <td>${new Date(cot.fecha_cotizacion).toLocaleDateString()}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning btn-editar-cotizacion" data-cotizacion-id="${cot.id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="eliminar_cotizacion" value="1">
                                <input type="hidden" name="cotizacion_id" value="${cot.id}">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar esta cotización?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                `;
                $('#tablaCotizaciones tbody').append(row);
            });
            
            // Configurar botones de edición
            $('.btn-editar-cotizacion').click(function() {
                let cotizacionId = $(this).data('cotizacion-id');
                
                // Buscar la cotización en todas las partidas
                let cotizacionSeleccionada = null;
                Object.values(cotizaciones).forEach(function(cots) {
                    cots.forEach(function(cot) {
                        if (cot.id == cotizacionId) {
                            cotizacionSeleccionada = cot;
                        }
                    });
                });
                
                if (cotizacionSeleccionada) {
                    // Cerrar modal actual
                    $('#verCotizacionesModal').modal('hide');
                    
                    // Configurar modal de cotización con los datos
                    setTimeout(function() {
                        let partidaId = cotizacionSeleccionada.partida_requisicion_id;
                        let partida = partidas[partidaId];
                        
                        $('#partida_id').val(partidaId);
                        $('#descripcion-partida').text(partida.descripcion);
                        $('#cantidad-partida').text(partida.cantidad_aprobada);
                        $('#unidad-partida').text(partida.unidad);
                        
                        $('#proveedor_id').val(cotizacionSeleccionada.proveedor_id);
                        $('#moneda').val(cotizacionSeleccionada.moneda);
                        $('#precio_unitario').val(cotizacionSeleccionada.precio_unitario);
                        $('#precio_total').val(cotizacionSeleccionada.precio_total);
                        $('#condiciones_pago').val(cotizacionSeleccionada.condiciones_pago);
                        $('#tiempo_entrega').val(cotizacionSeleccionada.tiempo_entrega);
                        $('#cotizo').val(cotizacionSeleccionada.cotizo);
                        $('#instrucciones').val(cotizacionSeleccionada.instrucciones);
                        
                        // Mostrar modal de cotización
                        $('#cotizacionModal').modal('show');
                    }, 500);
                }
            });
        });
        
        // Guardar cotización
        $('#btnGuardarCotizacion').click(function() {
            // Validar formulario
            if (!$('#formCotizacion')[0].checkValidity()) {
                $('#formCotizacion')[0].reportValidity();
                return;
            }
            
            // Enviar formulario
            $('#formCotizacion').submit();
        });
        
        // Cambio de color automático
        $('.color-select').change(function() {
            $(this).closest('form').submit();
        });
    });
</script>

<?php include 'footer.php'; ?>