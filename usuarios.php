<?php
require_once "config.php";
exigirRol("administrador");
$seccion_activa = "usuarios";

$error = ""; $exito = "";
$mi_id = (int)$_SESSION["id_usuario"];

// Roles que un administrador puede asignar desde esta pantalla.
// "administrador" NUNCA aparece aquí — ni en el <select> del HTML
// ni en esta lista blanca del servidor, así que aunque alguien
// manipule el formulario y mande rol=administrador por POST, el
// servidor lo ignora y usa "estudiante" por defecto.
$ROLES_ASIGNABLES = ["estudiante", "docente"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_usuario = $_POST["id_usuario"] ?? "";
    $nombre     = trim($_POST["nombre"] ?? "");
    $correo     = trim($_POST["correo"] ?? "");
    $rol        = in_array($_POST["rol"] ?? "", $ROLES_ASIGNABLES, true) ? $_POST["rol"] : "estudiante";
    $sexo       = $_POST["sexo"] ?? "";
    $edad       = $_POST["edad"] ?? "";
    $estatura   = $_POST["estatura"] ?? "";
    $peso       = $_POST["peso"] ?? "";

    // El objetivo y el grado son cosas de ESTUDIANTE (un docente no
    // tiene objetivo físico ni grado propio). Si el rol elegido es
    // docente, el servidor ignora lo que haya llegado en esos campos
    // y los guarda como NULL, sin importar lo que mande el formulario.
    $objetivo   = ($rol === "estudiante") ? ($_POST["objetivo"] ?? "") : null;
    $grado      = ($rol === "estudiante") ? ($_POST["grado"] ?? "") : null;
    $grado_db   = ($grado === "" || $grado === null) ? null : (int)$grado;

    if ($nombre === "" || strpos(trim($nombre), " ") === false) {
        $error = "El nombre debe incluir al menos nombre y apellido.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Ingresa un correo válido.";
    } elseif ($sexo === "" || $edad === "" || $estatura === "" || $peso === "") {
        $error = "Todos los campos de perfil son obligatorios.";
    } elseif ($rol === "estudiante" && ($objetivo === "" || $grado_db === null || !in_array($grado_db, [6,7,8,9,10,11], true))) {
        $error = "Para un estudiante, el objetivo y el grado (de sexto a once) son obligatorios.";
    } else {
        if ($id_usuario === "") {
            $temporal = $_POST["contrasena_temporal"] ?? "";
            if (strlen($temporal) < 8 || !preg_match('/[^A-Za-z0-9]/', $temporal)) {
                $error = "La contraseña temporal debe tener mínimo 8 caracteres y al menos un carácter especial.";
            } else {
                $verifica = mysqli_prepare($conexion, "SELECT id_usuario FROM usuario WHERE correo_electronico=? LIMIT 1");
                mysqli_stmt_bind_param($verifica, "s", $correo);
                mysqli_stmt_execute($verifica);
                mysqli_stmt_store_result($verifica);
                if (mysqli_stmt_num_rows($verifica) > 0) {
                    $error = "Ese correo ya está registrado.";
                } else {
                    $hash = password_hash($temporal, PASSWORD_DEFAULT);
                    mysqli_begin_transaction($conexion);
                    $stmt = mysqli_prepare($conexion, "INSERT INTO usuario (nombre, contrasena, correo_electronico, rol) VALUES (?,?,?,?)");
                    mysqli_stmt_bind_param($stmt, "ssss", $nombre, $hash, $correo, $rol);
                    mysqli_stmt_execute($stmt);
                    $nuevo_id = mysqli_insert_id($conexion);

                    $stmt2 = mysqli_prepare($conexion, "INSERT INTO perfil (sexo, edad, estatura, peso, objetivo, grado, id_usuario) VALUES (?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($stmt2, "siiisii", $sexo, $edad, $estatura, $peso, $objetivo, $grado_db, $nuevo_id);
                    mysqli_stmt_execute($stmt2);

                    $stmt3 = mysqli_prepare($conexion, "INSERT INTO racha (dias_consecutivos, fecha_inicio, fecha_ultima, nivel_personaje, id_usuario) VALUES (0, NULL, NULL, 'Bronce', ?)");
                    mysqli_stmt_bind_param($stmt3, "i", $nuevo_id);
                    mysqli_stmt_execute($stmt3);

                    mysqli_commit($conexion);
                    $exito = "Usuario creado correctamente.";
                }
            }
        } else {
            // Blindaje extra: aunque alguien arme una petición a mano con el
            // id de un administrador, esta consulta solo puede tocar filas
            // con rol estudiante/docente (nunca administrador).
            mysqli_begin_transaction($conexion);
            $stmt = mysqli_prepare($conexion,
                "UPDATE usuario SET nombre=?, correo_electronico=?, rol=?
                 WHERE id_usuario=? AND rol IN ('estudiante','docente')");
            mysqli_stmt_bind_param($stmt, "sssi", $nombre, $correo, $rol, $id_usuario);
            mysqli_stmt_execute($stmt);

            $stmt2 = mysqli_prepare($conexion, "UPDATE perfil SET sexo=?, edad=?, estatura=?, peso=?, objetivo=?, grado=? WHERE id_usuario=?");
            mysqli_stmt_bind_param($stmt2, "siiisii", $sexo, $edad, $estatura, $peso, $objetivo, $grado_db, $id_usuario);
            mysqli_stmt_execute($stmt2);
            mysqli_commit($conexion);
            $exito = "Usuario actualizado correctamente.";
        }
    }
}

if (isset($_GET["eliminar"])) {
    $id = (int)$_GET["eliminar"];
    // Solo borra si el objetivo NO es administrador (regla: el panel de
    // usuarios no administra administradores).
    $stmt = mysqli_prepare($conexion, "DELETE FROM usuario WHERE id_usuario = ? AND rol IN ('estudiante','docente')");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: usuarios.php?eliminado=1");
    exit;
}
if (isset($_GET["eliminado"])) { $exito = "Usuario eliminado correctamente."; }

$usuario_editar = null;
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conexion, "SELECT u.*, p.sexo, p.edad, p.estatura, p.peso, p.objetivo, p.grado
                                        FROM usuario u LEFT JOIN perfil p ON p.id_usuario = u.id_usuario
                                        WHERE u.id_usuario = ? AND u.rol IN ('estudiante','docente')");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $usuario_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// La lista NUNCA muestra cuentas de administrador: este panel es
// solo para gestionar estudiantes y docentes.
$usuarios = mysqli_query($conexion, "
  SELECT u.*, p.sexo, p.edad, p.estatura, p.peso, p.objetivo, p.grado, r.dias_consecutivos, r.nivel_personaje
  FROM usuario u
  LEFT JOIN perfil p ON p.id_usuario = u.id_usuario
  LEFT JOIN racha r ON r.id_usuario = u.id_usuario
  WHERE u.rol IN ('estudiante','docente')
  ORDER BY u.fecha_registro DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Usuarios</h1><p>Gestiona estudiantes y docentes, y asigna el rol de cada uno. (Los administradores no se gestionan aquí.)</p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="panel-card">
      <h2><?= $usuario_editar ? "Editar usuario" : "Nuevo usuario" ?></h2>
      <form method="POST">
        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($usuario_editar["id_usuario"] ?? "") ?>">
        <div class="form-grid">
          <div class="campo"><label>Nombre Completo</label>
            <input type="text" name="nombre" required value="<?= htmlspecialchars($usuario_editar["nombre"] ?? "") ?>"></div>
          <div class="campo"><label>Correo</label>
            <input type="email" name="correo" required value="<?= htmlspecialchars($usuario_editar["correo_electronico"] ?? "") ?>"></div>
          <div class="campo"><label>Rol</label>
            <select name="rol" id="campo-rol" onchange="actualizarCamposPorRol()">
              <option value="estudiante" <?= (($usuario_editar["rol"] ?? "estudiante") === "estudiante") ? "selected" : "" ?>>Estudiante</option>
              <option value="docente" <?= (($usuario_editar["rol"] ?? "") === "docente") ? "selected" : "" ?>>Docente</option>
            </select>
            <small class="ayuda-campo">Solo estudiante o docente — el rol administrador no se asigna desde aquí.</small>
            </div>
          <div class="campo"><label>Sexo</label>
            <select name="sexo">
              <?php foreach (["Masculino","Femenino","Otro"] as $g): ?>
                <option value="<?= $g ?>" <?= (($usuario_editar["sexo"] ?? "") === $g) ? "selected" : "" ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo"><label>Edad</label>
            <input type="number" name="edad" min="1" max="120" required value="<?= htmlspecialchars($usuario_editar["edad"] ?? "") ?>"></div>
          <div class="campo"><label>Estatura (cm)</label>
            <input type="number" name="estatura" required value="<?= htmlspecialchars($usuario_editar["estatura"] ?? "") ?>"></div>
          <div class="campo"><label>Peso (kg)</label>
            <input type="number" name="peso" required value="<?= htmlspecialchars($usuario_editar["peso"] ?? "") ?>"></div>
          <div class="campo campo-solo-estudiante"><label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= (($usuario_editar["objetivo"] ?? "") === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo campo-solo-estudiante"><label>Grado</label>
            <select name="grado">
              <option value="" disabled <?= !isset($usuario_editar["grado"]) ? "selected" : "" ?>>Selecciona el grado</option>
              <?php foreach ([6=>"Sexto",7=>"Séptimo",8=>"Octavo",9=>"Noveno",10=>"Décimo",11=>"Once"] as $num => $etq): ?>
                <option value="<?= $num ?>" <?= ((int)($usuario_editar["grado"] ?? 0) === $num) ? "selected" : "" ?>><?= $etq ?></option>
              <?php endforeach; ?>
            </select>
            <small class="ayuda-campo">Define qué docente lo tiene a cargo.</small>
            </div>
          <?php if (!$usuario_editar): ?>
          <div class="campo full"><label>Contraseña temporal (mín. 8 y un carácter especial)</label>
            <input type="password" name="contrasena_temporal" placeholder="Ej: Rendi*2026"></div>
          <?php endif; ?>
        </div>
        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm"><?= $usuario_editar ? "Guardar cambios" : "Crear usuario" ?></button>
          <?php if ($usuario_editar): ?><a href="usuarios.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <script>
      // El objetivo y el grado son solo de estudiante. Esto es nada
      // más una comodidad visual (ocultar/mostrar el campo); la
      // validación real, la que importa de verdad, ya la hace el
      // servidor en usuarios.php sin confiar en lo que mande el navegador.
      function actualizarCamposPorRol() {
        var esEstudiante = document.getElementById("campo-rol").value === "estudiante";
        document.querySelectorAll(".campo-solo-estudiante").forEach(function (campo) {
          campo.style.display = esEstudiante ? "" : "none";
          var input = campo.querySelector("select, input");
          if (input) { input.disabled = !esEstudiante; }
        });
      }
      actualizarCamposPorRol();
    </script>

    <div class="panel-card">
      <h2>Listado de usuarios</h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Edad</th><th>Objetivo / Grado</th><th>Rango</th><th>Racha</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($usuarios) === 0): ?>
              <tr><td colspan="8" class="vacio-tabla">Aún no hay usuarios registrados.</td></tr>
            <?php else: while ($u = mysqli_fetch_assoc($usuarios)): ?>
              <tr>
                <td><?= htmlspecialchars($u["nombre"]) ?></td>
                <td><?= htmlspecialchars($u["correo_electronico"]) ?></td>
                <td><span class="pill <?= $u["rol"] === "docente" ? "" : "gris" ?>"><?= ucfirst($u["rol"]) ?></span></td>
                <td><?= (int)$u["edad"] ?></td>
                <td><?php if ($u["rol"] === "estudiante"): ?>
                      <?= htmlspecialchars($u["objetivo"] ?? "—") ?><?= $u["grado"] ? " · " . (int)$u["grado"] . "°" : "" ?>
                    <?php else: ?>—<?php endif; ?></td>
                <td><?php if ($u["rol"] === "estudiante"): ?><span class="pill"><?= htmlspecialchars($u["nivel_personaje"] ?? "Bronce") ?></span><?php else: ?>—<?php endif; ?></td>
                <td><?= $u["rol"] === "estudiante" ? (int)($u["dias_consecutivos"] ?? 0) . " días" : "—" ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="usuarios.php?editar=<?= $u['id_usuario'] ?>">Editar</a>
                  <a class="eliminar" href="usuarios.php?eliminar=<?= $u['id_usuario'] ?>" onclick="return confirm('¿Eliminar este usuario?');">Eliminar</a>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
