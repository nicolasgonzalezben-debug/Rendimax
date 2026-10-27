<?php
require_once "config.php";


const CLAVE_INSTALACION = "RendiMAX-2026-SoloLosCreadores";

$ya_hay_admins = mysqli_fetch_assoc(
    mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuario WHERE rol='administrador'")
)["n"] ?? 0;

$error = ""; $exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && (int)$ya_hay_admins === 0) {
    $clave = $_POST["clave_instalacion"] ?? "";

    if (!hash_equals(CLAVE_INSTALACION, $clave)) {
        $error = "Clave de instalación incorrecta.";
    } else {
        $creados = 0;
        mysqli_begin_transaction($conexion);
        try {
            for ($i = 1; $i <= 4; $i++) {
                $nombre    = trim($_POST["nombre_$i"] ?? "");
                $correo    = trim($_POST["correo_$i"] ?? "");
                $clave_adm = $_POST["clave_$i"] ?? "";

                if ($nombre === "" || $correo === "" || $clave_adm === "") {
                    throw new Exception("Completa nombre, correo y contraseña de los 4 administradores.");
                }
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("El correo de \"$nombre\" no es válido.");
                }
                if (strlen($clave_adm) < 8 || !preg_match('/[^A-Za-z0-9]/', $clave_adm)) {
                    throw new Exception("La contraseña de \"$nombre\" debe tener mínimo 8 caracteres y un carácter especial.");
                }

                $hash = password_hash($clave_adm, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO usuario (nombre, contrasena, correo_electronico, rol) VALUES (?,?,?,'administrador')");
                mysqli_stmt_bind_param($stmt, "sss", $nombre, $hash, $correo);
                mysqli_stmt_execute($stmt);
                $creados++;
            }
            mysqli_commit($conexion);
            $exito = "Se crearon $creados administradores correctamente. Ahora pueden borrar este archivo (instalar_admins.php) del servidor.";
            $ya_hay_admins = 4;
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalar Administradores | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo"><img src="img/logo-rendimax.png" alt="RendiMAX"><span>Rendi<b>MAX</b></span></div>
  <div class="registro-card" style="max-width:720px;">
    <h2>Configuración inicial: los 4 administradores</h2>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ((int)$ya_hay_admins > 0): ?>
      <p style="text-align:center; font-size:14px;">
        Ya existen administradores en el sistema. Por seguridad, este script no se puede
        volver a usar. Si necesitas crear un docente o cambiar un rol, hazlo desde
        <strong>Usuarios</strong> dentro del panel, con una cuenta administradora.
      </p>
    <?php else: ?>
      <p style="font-size:13px; text-align:center; margin-bottom:20px;">
        Este formulario solo funciona la primera vez (cuando no hay ningún administrador
        todavía) y solo si conoces la clave de instalación.
      </p>
      <form method="POST">
        <div class="campo full" style="margin-bottom:22px;">
          <label>Clave de instalación</label>
          <input type="password" name="clave_instalacion" required>
        </div>

        <?php for ($i = 1; $i <= 4; $i++): ?>
          <h3 style="font-size:14px; margin:18px 0 10px;">Administrador <?= $i ?></h3>
          <div class="grid-2">
            <div class="campo"><label>Nombre completo</label><input type="text" name="nombre_<?= $i ?>" required></div>
            <div class="campo"><label>Correo</label><input type="email" name="correo_<?= $i ?>" required></div>
            <div class="campo full"><label>Contraseña (mín. 8 caracteres y un carácter especial)</label>
              <input type="password" name="clave_<?= $i ?>" minlength="8" required></div>
          </div>
        <?php endfor; ?>

        <div style="margin-top:16px;">
          <button type="submit" class="btn btn-verde">Crear los 4 administradores</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
