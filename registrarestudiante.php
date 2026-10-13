<?php
require_once "config.php";

$mensaje_error = "";
if (isset($_GET["error"])) {
    switch ($_GET["error"]) {
        case "vacio":          $mensaje_error = "Todos los campos son obligatorios."; break;
        case "nombre":         $mensaje_error = "Escribe tu nombre y apellido, separados por un espacio."; break;
        case "correo_formato": $mensaje_error = "Ingresa un correo válido (ej: nombre@correo.com)."; break;
        case "clave":          $mensaje_error = "Las contraseñas no coinciden."; break;
        case "clave_formato":  $mensaje_error = "La contraseña debe tener mínimo 8 caracteres y al menos un carácter especial."; break;
        case "correo":         $mensaje_error = "Ese correo ya está registrado."; break;
        case "grado":          $mensaje_error = "Selecciona tu grado (de sexto a once)."; break;
        default:               $mensaje_error = "Ocurrió un error al registrar la cuenta.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrar Estudiante</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-page">

  <a href="index.html" class="btn-volver-index">← Volver al inicio</a>

  <div class="auth-logo">
    <img src="img/logo-rendimax.png" alt="RendiMAX">
    <span>Rendi<b>MAX</b></span>
  </div>

  <p class="auth-titulo-pagina">Registrar</p>

  <div class="registro-card">

    <?php if ($mensaje_error): ?><div class="mensaje error"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>

    <form action="procesar_registro.php" method="POST" novalidate>
      <div class="grid-2">

        <div class="campo">
          <label for="nombre_completo">Nombre Completo</label>
          <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombre Completo"
                 pattern="^\S+(\s+\S+)+$" title="Escribe nombre y apellido, separados por un espacio" required>
          <small class="ayuda-campo">Nombre y apellido, separados por un espacio.</small>
        </div>

        <div class="campo">
          <label for="correo">Correo</label>
          <input type="email" id="correo" name="correo" placeholder="Correo" required>
          <small class="ayuda-campo">Debe ser un correo válido.</small>
        </div>

        <div class="campo">
          <label for="genero">Género</label>
          <select id="genero" name="genero" required>
            <option value="" disabled selected>Género</option>
            <option value="Masculino">Masculino</option>
            <option value="Femenino">Femenino</option>
            <option value="Otro">Otro</option>
          </select>
        </div>

        <div class="campo">
          <label for="edad">Edad</label>
          <input type="number" id="edad" name="edad" placeholder="Edad" min="1" max="120" required>
        </div>

        <div class="campo">
          <label for="estatura">Estatura (cm)</label>
          <input type="number" id="estatura" name="estatura" placeholder="Estatura (cm)" required>
        </div>

        <div class="campo">
          <label for="peso">Peso (kg)</label>
          <input type="number" id="peso" name="peso" placeholder="Peso (kg)" required>
        </div>

        <div class="campo">
          <label for="grado">Grado</label>
          <select id="grado" name="grado" required>
            <option value="" disabled selected>¿En qué grado estás?</option>
            <option value="6">Sexto</option>
            <option value="7">Séptimo</option>
            <option value="8">Octavo</option>
            <option value="9">Noveno</option>
            <option value="10">Décimo</option>
            <option value="11">Once</option>
          </select>
          <small class="ayuda-campo">Así sabemos qué docente te tiene a cargo.</small>
        </div>

        <div class="campo full">
          <label for="objetivo">Objetivo</label>
          <select id="objetivo" name="objetivo" required>
            <option value="" disabled selected>¿Cuál es tu objetivo?</option>
            <option value="Bajar de peso">Bajar de peso</option>
            <option value="Ganar músculo">Ganar músculo</option>
            <option value="Mantenimiento">Mantenimiento</option>
          </select>
        </div>

        <div class="campo">
          <label for="contrasena">Contraseña</label>
          <input type="password" id="contrasena" name="contrasena" placeholder="Contraseña"
                 minlength="8" pattern="^(?=.*[^A-Za-z0-9]).{8,}$"
                 title="Mínimo 8 caracteres e incluye al menos un carácter especial" required>
          <small class="ayuda-campo">Mínimo 8 caracteres y un carácter especial (ej: * # @ .).</small>
        </div>

        <div class="campo">
          <label for="confirmar_contrasena">Confirmar Contraseña</label>
          <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Confirmar Contraseña" required>
        </div>

      </div>

      <div style="margin-top:10px;">
        <button type="submit" class="btn btn-verde">Registrar</button>
      </div>
    </form>

  </div>
</div>

</body>
</html>
