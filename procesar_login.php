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

// El rol se guarda en la sesión TAL COMO está en la base de datos.
// Nunca llega desde $_POST/$_GET: por eso nadie puede "hacerse pasar"
// por administrador enviando un campo oculto o editando la URL.
//
// session_regenerate_id(true) crea un ID de sesión totalmente nuevo
// y borra el archivo de sesión anterior en el servidor. Esto es
// clave: si no se hace, es posible que quede "pegada" información
// vieja (por ejemplo, un rol que cambiaste en la base de datos pero
// que la sesión anterior nunca se enteró). Así, cada login arranca
// de cero, garantizado.
session_regenerate_id(true);

$_SESSION["id_usuario"] = $usuario["id_usuario"];
$_SESSION["nombre"]     = $usuario["nombre"];
$_SESSION["correo"]     = $usuario["correo_electronico"];
// strtolower() por seguridad extra: así, aunque la base de datos
// devuelva el rol con otra capitalización (por ejemplo "Estudiante"
// en vez de "estudiante"), el sistema lo sigue reconociendo bien.
$_SESSION["rol"]        = strtolower($usuario["rol"]);

header("Location: dashboard.php");
exit;
