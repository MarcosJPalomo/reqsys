<?php
// header.php - Encabezado común para todas las páginas
require_once 'config.php';
verificarSesion();

$usuario_actual = obtenerUsuarioActual();
$alerta = obtenerAlertas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Requisiciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            background-color: #343a40;
            padding-top: 56px;
            transition: all 0.3s;
            z-index: 9;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 12px 20px;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .sidebar-heading {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            padding-top: 76px;
        }
        
        .navbar {
            padding: 10px 20px;
            background-color: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 10;
            position: fixed;
            width: 100%;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.125);
            font-weight: bold;
            padding: 15px 20px;
        }
        
        .badge-counter {
            position: absolute;
            transform: scale(0.85);
            transform-origin: top right;
            right: 0;
            margin-top: -0.5rem;
        }
        
        .table {
            vertical-align: middle;
        }
        
        .btn-circle {
            border-radius: 100%;
            height: 2.5rem;
            width: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .estado-pendiente {
            background-color: #ffeeba;
        }
        
        .estado-aprobado {
            background-color: #c3e6cb;
        }
        
        .estado-rechazado {
            background-color: #f5c6cb;
        }
        
        /* Estilos específicos para formularios */
        .form-select, .form-control {
            padding: 0.5rem 0.75rem;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        
        .btn {
            border-radius: 5px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        /* Dropdown personalizado */
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        
        .dropdown-item {
            padding: 8px 15px;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        /* Tooltips personalizados */
        .tooltip-inner {
            background-color: #343a40;
        }
        
        /* Colores de partidas */
        .partida-verde {
            background-color: rgba(25, 135, 84, 0.2) !important;
        }
        
        .partida-amarillo {
            background-color: rgba(255, 193, 7, 0.2) !important;
        }
        
        .partida-rojo {
            background-color: rgba(220, 53, 69, 0.2) !important;
        }
        
        .partida-azul {
            background-color: rgba(13, 110, 253, 0.2) !important;
        }
        
        .partida-gris {
            background-color: rgba(108, 117, 125, 0.2) !important;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Sistema de Requisiciones</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificacionesDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php 
                            // Aquí se agregaría código para contar notificaciones
                            $count_notificaciones = 0; 
                            if($count_notificaciones > 0):
                            ?>
                            <span class="badge bg-danger badge-counter"><?php echo $count_notificaciones; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">No hay notificaciones nuevas</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="usuarioDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person"></i> Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar & Contenido Principal -->
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav class="sidebar">
            <?php if ($_SESSION['rol'] === 'solicitante'): ?>
                <div class="sidebar-heading">Solicitudes</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="solicitud_nueva.php"><i class="bi bi-plus-circle"></i> Nueva Solicitud</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mis_solicitudes.php"><i class="bi bi-list-check"></i> Mis Solicitudes</a>
                    </li>
                </ul>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'almacen'): ?>
                <div class="sidebar-heading">Almacén</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="solicitudes_pendientes.php"><i class="bi bi-clipboard-check"></i> Solicitudes Pendientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requisiciones.php"><i class="bi bi-file-earmark-text"></i> Requisiciones</a>
                    </li>
                </ul>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'operaciones'): ?>
                <div class="sidebar-heading">Operaciones</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requisiciones_pendientes.php"><i class="bi bi-clipboard-check"></i> Requisiciones Pendientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requisiciones_aprobadas.php"><i class="bi bi-check-circle"></i> Requisiciones Aprobadas</a>
                    </li>
                </ul>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'compras'): ?>
                <div class="sidebar-heading">Compras</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requisiciones_por_cotizar.php"><i class="bi bi-cash-coin"></i> Requisiciones por Cotizar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cotizaciones.php"><i class="bi bi-file-earmark-spreadsheet"></i> Cotizaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="proveedores.php"><i class="bi bi-building"></i> Proveedores</a>
                    </li>
                </ul>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'abastecimiento'): ?>
                <div class="sidebar-heading">Abastecimiento</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="requisiciones_cotizadas.php"><i class="bi bi-clipboard-check"></i> Requisiciones Cotizadas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historial_ordenes.php"><i class="bi bi-journal-text"></i> Historial de Órdenes</a>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>

        <!-- Contenido Principal -->
        <div class="main-content">
            <?php if(isset($alerta)): ?>
            <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                <?php echo $alerta['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>