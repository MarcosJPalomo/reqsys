<?php
// config.php - Archivo de configuración

// Datos de conexión a la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_NAME', 'reqsys');

// Conexión a la base de datos
function conectarDB() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        die('Error de conexión a la base de datos: ' . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8");
    return $mysqli;
}

// Función para sanitizar entradas
function sanitizar($db, $input) {
    if (is_array($input)) {
        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitized[$key] = sanitizar($db, $value);
        }
        return $sanitized;
    } else {
        return $db->real_escape_string(trim($input));
    }
}

// Función para verificar sesión activa
// Función para verificar sesión activa
function verificarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    return true;
}

// Función para verificar rol de usuario
function verificarRol($roles_permitidos) {
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
        header('Location: acceso_denegado.php');
        exit;
    }
    return true;
}

// Función para mostrar mensajes de alerta
function mostrarAlerta($tipo, $mensaje) {
    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

// Función para obtener alertas y limpiarlas de la sesión
function obtenerAlertas() {
    $alerta = isset($_SESSION['alerta']) ? $_SESSION['alerta'] : null;
    unset($_SESSION['alerta']);
    return $alerta;
}

// Obtener información de usuario actual
function obtenerUsuarioActual() {
    if (isset($_SESSION['usuario_id'])) {
        $db = conectarDB();
        $id = (int)$_SESSION['usuario_id'];
        $query = "SELECT * FROM usuarios WHERE id = $id";
        $resultado = $db->query($query);
        
        if ($resultado && $resultado->num_rows > 0) {
            return $resultado->fetch_assoc();
        }
    }
    return null;
}

// Función para formatear fechas
function formatearFecha($fecha, $formato = 'd/m/Y') {
    $date = new DateTime($fecha);
    return $date->format($formato);
}