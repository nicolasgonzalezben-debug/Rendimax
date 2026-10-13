<?php
require_once "config.php";

if (isset($_SESSION["id_usuario"])) {
    header("Location: dashboard.php");
    exit;
}

$mensaje_exito = "";
if (isset($_GET["registrado"])) {
    $mensaje_exito = "Cuenta creada correctamente. Ahora puedes iniciar sesión.";
}

$mensaje_error = "";
if (isset($_GET["error"])) {
    switch ($_GET["error"]) {
        case "vacio":        $mensaje_error = "Completa correo/nombre y contraseña."; break;
        case "credenciales": $mensaje_error = "Correo/nombre o contraseña incorrectos."; break;
        case "sesion":       $mensaje_error = "Debes iniciar sesión para continuar."; break;
        default:             $mensaje_error = "Ocurrió un error al iniciar sesión.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar Sesión</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-page">

  <a href="index.html" class="btn-volver-index">← Volver al inicio</a>

  <div class="auth-logo">
    <img src="img/logo-rendimax.png" alt="RendiMAX">
    <span>Rendi<b>MAX</b></span>
  </div>

  <div class="auth-card">

    <div class="auth-panel-izq">
      <span class="badge">Salud, fuerza y motivación</span>
      <h2>Impulsa tu cuerpo y<br>enfoca tu mente</h2>
      <p>
        Este espacio está pensado para que cada paso cuente: organiza
        tus clases, registra tu progreso y mantén la energía para seguir
        adelante.
      </p>
    </div>

    <div class="auth-panel-der">
      <h2>Iniciar Sesión</h2>

      <?php if ($mensaje_exito): ?><div class="mensaje exito"><?= htmlspecialchars($mensaje_exito) ?></div><?php endif; ?>
      <?php if ($mensaje_error): ?><div class="mensaje error"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>

      <form action="procesar_login.php" method="POST" novalidate>
        <div class="campo">
          <label for="correo_o_nombre">Correo o Nombre</label>
          <input type="text" id="correo_o_nombre" name="correo_o_nombre" placeholder="Correo o Nombre" required>
        </div>
        <div class="campo">
          <label for="contrasena">Contraseña</label>
          <input type="password" id="contrasena" name="contrasena" placeholder="Contraseña" required>
        </div>
        <button type="submit" class="btn btn-verde">Ingresar</button>
      </form>

      <div class="auth-link-crear">
        <a href="registrarestudiante.php">Crear Cuenta</a>
      </div>
    </div>

  </div>
</div>

</body>
</html>
