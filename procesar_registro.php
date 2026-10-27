<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: registrarestudiante.php");
    exit;
}

$nombre_completo      = trim($_POST["nombre_completo"] ?? "");
$correo               = trim($_POST["correo"] ?? "");
$genero               = $_POST["genero"] ?? "";
$edad                 = $_POST["edad"] ?? "";
$estatura             = $_POST["estatura"] ?? "";
$peso                 = $_POST["peso"] ?? "";
$objetivo             = $_POST["objetivo"] ?? "";
$grado                = $_POST["grado"] ?? "";
$contrasena           = $_POST["contrasena"] ?? "";
$confirmar_contrasena = $_POST["confirmar_contrasena"] ?? "";

if ($nombre_completo === "" || $correo === "" || $genero === "" ||
    $edad === "" || $estatura === "" || $peso === "" || $objetivo === "" || $grado === "" ||
    $contrasena === "" || $confirmar_contrasena === "") {
    header("Location: registrarestudiante.php?error=vacio");
    exit;
}

if (!in_array((int)$grado, [6,7,8,9,10,11], true)) {
    header("Location: registrarestudiante.php?error=grado");
    exit;
}

$nombre_completo = preg_replace('/\s+/', ' ', $nombre_completo);
$partes_nombre = array_filter(explode(' ', $nombre_completo));
if (strpos($nombre_completo, ' ') === false || count($partes_nombre) < 2) {
    header("Location: registrarestudiante.php?error=nombre");
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header("Location: registrarestudiante.php?error=correo_formato");
    exit;
}

if ($contrasena !== $confirmar_contrasena) {
    header("Location: registrarestudiante.php?error=clave");
    exit;
}

if (strlen($contrasena) < 8 || !preg_match('/[^A-Za-z0-9]/', $contrasena)) {
    header("Location: registrarestudiante.php?error=clave_formato");
    exit;
}

$verifica = mysqli_prepare($conexion, "SELECT id_usuario FROM usuario WHERE correo_electronico = ? LIMIT 1");
mysqli_stmt_bind_param($verifica, "s", $correo);
mysqli_stmt_execute($verifica);
mysqli_stmt_store_result($verifica);
if (mysqli_stmt_num_rows($verifica) > 0) {
    header("Location: registrarestudiante.php?error=correo");
    exit;
}

$contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);


$rol = "estudiante";

mysqli_begin_transaction($conexion);
try {
    $sql = "INSERT INTO usuario (nombre, contrasena, correo_electronico, rol) VALUES (?,?,?,?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $nombre_completo, $contrasena_hash, $correo, $rol);
    mysqli_stmt_execute($stmt);
    $id_usuario = mysqli_insert_id($conexion);

    $sql2 = "INSERT INTO perfil (sexo, edad, estatura, peso, objetivo, grado, id_usuario) VALUES (?,?,?,?,?,?,?)";
    $stmt2 = mysqli_prepare($conexion, $sql2);
    mysqli_stmt_bind_param($stmt2, "siiisii", $genero, $edad, $estatura, $peso, $objetivo, $grado, $id_usuario);
    mysqli_stmt_execute($stmt2);

    
    $sql3 = "INSERT INTO racha (dias_consecutivos, fecha_inicio, fecha_ultima, nivel_personaje, id_usuario) VALUES (0, NULL, NULL, 'Bronce', ?)";
    $stmt3 = mysqli_prepare($conexion, $sql3);
    mysqli_stmt_bind_param($stmt3, "i", $id_usuario);
    mysqli_stmt_execute($stmt3);

    mysqli_commit($conexion);
    header("Location: iniciarsesion.php?registrado=1");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conexion);
    header("Location: registrarestudiante.php?error=general");
    exit;
}
