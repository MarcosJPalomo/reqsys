<?php
// proveedores.php - Gestión de proveedores para usuario de compras
require_once 'config.php';
verificarSesion();
verificarRol(['compras']);

$db = conectarDB();

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Agregar nuevo proveedor
    if (isset($_POST['agregar_proveedor'])) {
        $nombre = sanitizar($db, $_POST['nombre']);
        $contacto = sanitizar($db, $_POST['contacto']);
        $telefono = sanitizar($db, $_POST['telefono']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? sanitizar($db, $_POST['email']) : '';
        $rfc = sanitizar($db, $_POST['rfc']);
        
        $query = "INSERT INTO proveedores (nombre, contacto, telefono, email, activo) 
                  VALUES ('$nombre', '$contacto', '$telefono', '$email', 1)";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Proveedor agregado correctamente');
        } else {
            mostrarAlerta('danger', 'Error al agregar proveedor: ' . $db->error);
        }
    }
    
    // Editar proveedor
    if (isset($_POST['editar_proveedor'])) {
        $id = (int)$_POST['proveedor_id'];
        $nombre = sanitizar($db, $_POST['nombre']);
        $contacto = sanitizar($db, $_POST['contacto']);
        $telefono = sanitizar($db, $_POST['telefono']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? sanitizar($db, $_POST['email']) : '';
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        $query = "UPDATE proveedores SET 
                  nombre = '$nombre', 
                  contacto = '$contacto', 
                  telefono = '$telefono', 
                  email = '$email', 
                  activo = $activo
                  WHERE id = $id";
        
        if ($db->query($query)) {
            mostrarAlerta('success', 'Proveedor actualizado correctamente');
        } else {
            mostrarAlerta('danger', 'Error al actualizar proveedor: ' . $db->error);
        }
    }
    
    // Recargar página para evitar reenvío de formulario
    header('Location: proveedores.php');
    exit;
}

// Obtener listado de proveedores
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM cotizaciones c WHERE c.proveedor_id = p.id) as total_cotizaciones,
          (SELECT COUNT(*) FROM cotizaciones c 
           JOIN partidas_requisicion pr ON c.partida_requisicion_id = pr.id
           WHERE c.proveedor_id = p.id AND c.seleccionada = 1) as cotizaciones_seleccionadas
          FROM proveedores p
          ORDER BY p.activo DESC, p.nombre";

$resultado = $db->query($query);
$proveedores = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

$db->close();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Administración de Proveedores</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#agregarProveedorModal">
        <i class="bi bi-plus-circle"></i> Nuevo Proveedor
    </button>
</div>

<div class="card shadow">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 text-gray-800">Listado de Proveedores</h5>
    </div>
    <div class="card-body">
        <?php if (empty($proveedores)): ?>
            <div class="alert alert-info">
                No hay proveedores registrados. Agregue un nuevo proveedor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Cotizaciones</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <tr class="<?php echo $proveedor['activo'] ? '' : 'table-secondary text-muted'; ?>">
                                <td><?php echo htmlspecialchars($proveedor['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['contacto']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['email']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $proveedor['total_cotizaciones']; ?></span>
                                    <?php if ($proveedor['cotizaciones_seleccionadas'] > 0): ?>
                                        <span class="badge bg-success ms-1">
                                            <?php echo $proveedor['cotizaciones_seleccionadas']; ?> seleccionadas
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($proveedor['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning btn-editar" 
                                            data-id="<?php echo $proveedor['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($proveedor['nombre']); ?>"
                                            data-contacto="<?php echo htmlspecialchars($proveedor['contacto']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($proveedor['telefono']); ?>"
                                            data-email="<?php echo htmlspecialchars($proveedor['email']); ?>"
                                            data-activo="<?php echo $proveedor['activo']; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editarProveedorModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Agregar Proveedor -->
<div class="modal fade" id="agregarProveedorModal" tabindex="-1" aria-labelledby="agregarProveedorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agregarProveedorModalLabel">Nuevo Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="agregar_proveedor" value="1">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contacto" class="form-label">Persona de Contacto</label>
                        <input type="text" class="form-control" id="contacto" name="contacto">
                    </div>
                    
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Proveedor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Proveedor -->
<div class="modal fade" id="editarProveedorModal" tabindex="-1" aria-labelledby="editarProveedorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarProveedorModalLabel">Editar Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="editar_proveedor" value="1">
                    <input type="hidden" id="edit_proveedor_id" name="proveedor_id">
                    
                    <div class="mb-3">
                        <label for="edit_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_contacto" class="form-label">Persona de Contacto</label>
                        <input type="text" class="form-control" id="edit_contacto" name="contacto">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="edit_telefono" name="telefono">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_activo" name="activo">
                        <label class="form-check-label" for="edit_activo">
                            Proveedor Activo
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Llenar modal de edición
        $('.btn-editar').click(function() {
            $('#edit_proveedor_id').val($(this).data('id'));
            $('#edit_nombre').val($(this).data('nombre'));
            $('#edit_contacto').val($(this).data('contacto'));
            $('#edit_telefono').val($(this).data('telefono'));
            $('#edit_email').val($(this).data('email'));
            $('#edit_activo').prop('checked', $(this).data('activo') == 1);
        });
    });
</script>

<?php include 'footer.php'; ?>