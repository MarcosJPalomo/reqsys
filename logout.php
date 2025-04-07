<?php
// logout.php - Cerrar sesión de usuario
require_once 'config.php';

session_start();
session_destroy();

header('Location: login.php');
exit;
?>