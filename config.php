<?php
/**
 * config.php
 * Conexión a la base de datos RendiMAX (MySQL / MariaDB - XAMPP).
 * Ajusta estos 4 valores según tu instalación local.
 */

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "rendimax";

// Conexión con mysqli
$conexion = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conexion) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");

// Inicia la sesión de PHP en todas las páginas que incluyan este archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carga verificarSesion(), exigirRol() y demás funciones de permisos.
// Así cualquier página que incluya config.php ya tiene el sistema de
// roles disponible sin tener que acordarse de incluirlo aparte.
require_once __DIR__ . "/funciones.php";
