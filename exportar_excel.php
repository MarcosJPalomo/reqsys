<?php
// exportar_excel.php - Generar reporte en Excel de requisiciones cotizadas
require_once 'config.php';
verificarSesion();
verificarRol(['compras', 'abastecimiento']);

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    mostrarAlerta('danger', 'ID de requisición no válido');
    header('Location: index.php');
    exit;
}

$db = conectarDB();
$requisicion_id = (int)$_GET['id'];

// Verificar que la requisición existe
$query = "SELECT r.*, u.nombre, u.apellido, c.folio_orden_compra
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

// Obtener cotizaciones seleccionadas
$query = "SELECT c.*, p.nombre as proveedor_nombre, pr.partida_original, pr.descripcion, pr.unidad, pr.cantidad_aprobada
          FROM cotizaciones c
          JOIN proveedores p ON c.proveedor_id = p.id
          JOIN partidas_requisicion par ON c.partida_requisicion_id = par.id
          JOIN (
              SELECT ps.id, ps.partida as partida_original, ps.descripcion, ps.unidad, pr.cantidad_aprobada
              FROM partidas_solicitud ps
              JOIN partidas_requisicion pr ON ps.id = pr.partida_solicitud_id
              WHERE pr.requisicion_id = $requisicion_id
          ) pr ON par.partida_solicitud_id = pr.id
          WHERE c.partida_requisicion_id IN (" . implode(',', array_keys($partidas)) . ")
          AND (c.seleccionada = 1 OR (SELECT COUNT(*) FROM cotizaciones WHERE partida_requisicion_id = c.partida_requisicion_id) = 1)
          ORDER BY c.partida_requisicion_id";

$resultado = $db->query($query);
$cotizaciones = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $cotizaciones[] = $row;
    }
}

// Establecer las cabeceras para descargar archivo Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="requisicion_' . $requisicion_id . '.xls"');
header('Cache-Control: max-age=0');
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .subheader {
            font-size: 14px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        Requisición #<?php echo $requisicion_id; ?> - Folio: <?php echo htmlspecialchars($requisicion['folio']); ?>
        <?php if (!empty($requisicion['folio_orden_compra'])): ?>
            - Orden de Compra: <?php echo htmlspecialchars($requisicion['folio_orden_compra']); ?>
        <?php endif; ?>
    </div>
    
    <div class="subheader">
        Creada por: <?php echo htmlspecialchars($requisicion['nombre'] . ' ' . $requisicion['apellido']); ?> | 
        Fecha: <?php echo formatearFecha($requisicion['fecha_requisicion']); ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Partida</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Unidad</th>
                <th>Proveedor</th>
                <th>Precio Unitario</th>
                <th>Precio Total</th>
                <th>Moneda</th>
                <th>Tiempo de Entrega</th>
                <th>Condiciones de Pago</th>
                <th>Instrucciones</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_general = 0;
            $moneda_principal = '';
            
            if (!empty($cotizaciones)) {
                $moneda_principal = $cotizaciones[0]['moneda'];
            }
            
            foreach ($cotizaciones as $cotizacion): 
                // Convertir el precio total a la moneda principal si es necesario
                $precio_total = $cotizacion['precio_total'];
                if ($cotizacion['moneda'] == $moneda_principal) {
                    $total_general += $precio_total;
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($cotizacion['partida_original']); ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['descripcion']); ?></td>
                    <td><?php echo number_format($cotizacion['cantidad_aprobada'], 2); ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['unidad']); ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['proveedor_nombre']); ?></td>
                    <td><?php echo number_format($cotizacion['precio_unitario'], 2); ?></td>
                    <td><?php echo number_format($cotizacion['precio_total'], 2); ?></td>
                    <td><?php echo $cotizacion['moneda']; ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['tiempo_entrega']); ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['condiciones_pago']); ?></td>
                    <td><?php echo htmlspecialchars($cotizacion['instrucciones']); ?></td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (!empty($cotizaciones)): ?>
            <tr>
                <td colspan="6" style="text-align: right;"><strong>Total General:</strong></td>
                <td><strong><?php echo number_format($total_general, 2); ?></strong></td>
                <td><?php echo $moneda_principal; ?></td>
                <td colspan="3"></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$db->close();
?>