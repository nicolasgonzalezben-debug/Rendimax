<?php
require_once "config.php";
exigirRol("estudiante");
$seccion_activa = "perfil";
$mi_id = (int)$_SESSION["id_usuario"];
$error = ""; $exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "datos") {
    $nombre = trim($_POST["nombre"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $edad = $_POST["edad"] ?? ""; $estatura = $_POST["estatura"] ?? ""; $peso = $_POST["peso"] ?? "";
    $objetivo = $_POST["objetivo"] ?? "";

    if ($nombre === "" || strpos(trim($nombre), " ") === false) {
        $error = "El nombre debe incluir al menos nombre y apellido.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Ingresa un correo válido.";
    } elseif ($edad === "" || $estatura === "" || $peso === "") {
        $error = "Todos los campos son obligatorios.";
    } else {
        $verifica = mysqli_prepare($conexion, "SELECT id_usuario FROM usuario WHERE correo_electronico=? AND id_usuario<>? LIMIT 1");
        mysqli_stmt_bind_param($verifica, "si", $correo, $mi_id);
        mysqli_stmt_execute($verifica);
        mysqli_stmt_store_result($verifica);
        if (mysqli_stmt_num_rows($verifica) > 0) {
            $error = "Ese correo ya lo usa otra cuenta.";
        } else {
            mysqli_begin_transaction($conexion);
            $stmt = mysqli_prepare($conexion, "UPDATE usuario SET nombre=?, correo_electronico=? WHERE id_usuario=?");
            mysqli_stmt_bind_param($stmt, "ssi", $nombre, $correo, $mi_id);
            mysqli_stmt_execute($stmt);
            $stmt2 = mysqli_prepare($conexion, "UPDATE perfil SET edad=?, estatura=?, peso=?, objetivo=? WHERE id_usuario=?");
            mysqli_stmt_bind_param($stmt2, "iiisi", $edad, $estatura, $peso, $objetivo, $mi_id);
            mysqli_stmt_execute($stmt2);
            mysqli_commit($conexion);
            $_SESSION["nombre"] = $nombre; $_SESSION["correo"] = $correo;
            $exito = "Tus datos se actualizaron correctamente.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "clave") {
    $actual = $_POST["actual"] ?? ""; $nueva = $_POST["nueva"] ?? ""; $confirmar = $_POST["confirmar"] ?? "";
    $stmt = mysqli_prepare($conexion, "SELECT contrasena FROM usuario WHERE id_usuario=?");
    mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$fila || !password_verify($actual, $fila["contrasena"])) {
        $error = "Tu contraseña actual no es correcta.";
    } elseif ($nueva !== $confirmar) {
        $error = "Las contraseñas nuevas no coinciden.";
    } elseif (strlen($nueva) < 8 || !preg_match('/[^A-Za-z0-9]/', $nueva)) {
        $error = "La nueva contraseña debe tener mínimo 8 caracteres y al menos un carácter especial.";
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $upd = mysqli_prepare($conexion, "UPDATE usuario SET contrasena=? WHERE id_usuario=?");
        mysqli_stmt_bind_param($upd, "si", $hash, $mi_id); mysqli_stmt_execute($upd);
        $exito = "Tu contraseña se actualizó correctamente.";
    }
}

$stmt = mysqli_prepare($conexion, "SELECT u.*, p.sexo, p.edad, p.estatura, p.peso, p.objetivo, p.grado
                                    FROM usuario u LEFT JOIN perfil p ON p.id_usuario=u.id_usuario WHERE u.id_usuario=?");
mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
$yo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Perfil | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Mi Perfil</h1><p>Consulta y edita tu propia información.</p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="panel-card">
      <h2>Mis datos</h2>
      <form method="POST">
        <input type="hidden" name="accion" value="datos">
        <div class="form-grid">
          <div class="campo"><label>Nombre Completo</label><input type="text" name="nombre" required value="<?= htmlspecialchars($yo["nombre"]) ?>"></div>
          <div class="campo"><label>Correo</label><input type="email" name="correo" required value="<?= htmlspecialchars($yo["correo_electronico"]) ?>"></div>
          <div class="campo"><label>Edad</label><input type="number" name="edad" min="1" max="120" required value="<?= htmlspecialchars($yo["edad"]) ?>"></div>
          <div class="campo"><label>Sexo</label><input type="text" value="<?= htmlspecialchars($yo["sexo"]) ?>" disabled></div>
          <div class="campo"><label>Grado</label><input type="text" value="<?= $yo["grado"] ? (int)$yo["grado"] . "°" : "—" ?>" disabled>
            <small class="ayuda-campo">Solo un administrador puede cambiar tu grado.</small></div>
          <div class="campo"><label>Estatura (cm)</label><input type="number" name="estatura" required value="<?= htmlspecialchars($yo["estatura"]) ?>"></div>
          <div class="campo"><label>Peso (kg)</label><input type="number" name="peso" required value="<?= htmlspecialchars($yo["peso"]) ?>"></div>
          <div class="campo full"><label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= ($yo["objetivo"] === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-acciones"><button type="submit" class="btn btn-verde btn-sm">Guardar cambios</button></div>
      </form>
    </div>

    <div class="panel-card">
      <h2>Cambiar mi contraseña</h2>
      <form method="POST">
        <input type="hidden" name="accion" value="clave">
        <div class="form-grid">
          <div class="campo full"><label>Contraseña actual</label><input type="password" name="actual" required></div>
          <div class="campo"><label>Nueva contraseña</label>
            <input type="password" name="nueva" minlength="8" pattern="^(?=.*[^A-Za-z0-9]).{8,}$" required></div>
          <div class="campo"><label>Confirmar nueva contraseña</label><input type="password" name="confirmar" required></div>
        </div>
        <p style="font-size:11.5px; color:#9aa4b2; margin:-6px 0 16px;">Mínimo 8 caracteres y un carácter especial.</p>
        <div class="form-acciones"><button type="submit" class="btn btn-verde btn-sm">Actualizar contraseña</button></div>
      </form>
    </div>
  </div>
</main>
</body>
</html>
