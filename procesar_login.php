<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: iniciarsesion.php");
    exit;
}

$correo_o_nombre = trim($_POST["correo_o_nombre"] ?? "");
$contrasena      = $_POST["contrasena"] ?? "";

if ($correo_o_nombre === "" || $contrasena === "") {
    header("Location: iniciarsesion.php?error=vacio");
    exit;
}

$sql = "SELECT id_usuario, nombre, correo_electronico, contrasena, rol
        FROM usuario
        WHERE correo_electronico = ? OR nombre = ?
        LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ss", $correo_o_nombre, $correo_o_nombre);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$usuario || !password_verify($contrasena, $usuario["contrasena"])) {
    header("Location: iniciarsesion.php?error=credenciales");
    exit;
}


session_regenerate_id(true);

$_SESSION["id_usuario"] = $usuario["id_usuario"];
$_SESSION["nombre"]     = $usuario["nombre"];
$_SESSION["correo"]     = $usuario["correo_electronico"];

$_SESSION["rol"]        = strtolower($usuario["rol"]);

header("Location: dashboard.php");
exit;
