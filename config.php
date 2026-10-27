<?php


$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "rendimax";

$conexion = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conexion) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/funciones.php";