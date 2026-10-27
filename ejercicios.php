<?php
require_once "config.php";
exigirRol(["docente", "administrador"]);
$seccion_activa = "ejercicios";

$error = ""; $exito = "";
$mi_id    = (int)$_SESSION["id_usuario"];
$es_admin = esAdministrador();


function puedeEditarEjercicio($ejercicio, $esAdmin, $miId) {
    return $esAdmin || (int)$ejercicio["id_usuario"] === (int)$miId;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_ejercicio  = $_POST["id_ejercicio"] ?? "";
    $nombre        = trim($_POST["nombre"] ?? "");
    $tipo          = $_POST["tipo"] ?? "Fuerza";
    $instrucciones = trim($_POST["instrucciones"] ?? "");
    $descripcion   = trim($_POST["descripcion"] ?? "");

    if ($nombre === "") {
        $error = "El nombre del ejercicio es obligatorio.";
    } elseif ($id_ejercicio === "") {
        $sql = "INSERT INTO ejercicios (nombre, tipo, instrucciones, descripcion, id_usuario) VALUES (?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $tipo, $instrucciones, $descripcion, $mi_id);
        mysqli_stmt_execute($stmt);
        $exito = "Ejercicio creado correctamente.";
    } else {
       
        $stmt0 = mysqli_prepare($conexion, "SELECT id_usuario FROM ejercicios WHERE id_ejercicio = ?");
        mysqli_stmt_bind_param($stmt0, "i", $id_ejercicio);
        mysqli_stmt_execute($stmt0);
        $fila0 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt0));

        if (!$fila0 || !puedeEditarEjercicio($fila0, $es_admin, $mi_id)) {
            $error = "Solo puedes editar los ejercicios que tú mismo creaste.";
        } else {
            $sql = "UPDATE ejercicios SET nombre=?, tipo=?, instrucciones=?, descripcion=? WHERE id_ejercicio=?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $tipo, $instrucciones, $descripcion, $id_ejercicio);
            mysqli_stmt_execute($stmt);
            $exito = "Ejercicio actualizado correctamente.";
        }
    }
}

if (isset($_GET["eliminar"])) {
    $id = (int)$_GET["eliminar"];
    $stmt0 = mysqli_prepare($conexion, "SELECT id_usuario FROM ejercicios WHERE id_ejercicio = ?");
    mysqli_stmt_bind_param($stmt0, "i", $id);
    mysqli_stmt_execute($stmt0);
    $fila0 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt0));

    if (!$fila0 || !puedeEditarEjercicio($fila0, $es_admin, $mi_id)) {
        header("Location: ejercicios.php?sinpermiso=1");
        exit;
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM ejercicios WHERE id_ejercicio = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: ejercicios.php?eliminado=1");
    exit;
}
if (isset($_GET["eliminado"])) { $exito = "Ejercicio eliminado correctamente."; }
if (isset($_GET["sinpermiso"])) { $error = "Solo puedes eliminar los ejercicios que tú mismo creaste."; }

$ejercicio_editar = null;
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM ejercicios WHERE id_ejercicio = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($fila && puedeEditarEjercicio($fila, $es_admin, $mi_id)) {
        $ejercicio_editar = $fila;
    } else {
        $error = "Solo puedes editar los ejercicios que tú mismo creaste.";
    }
}

$ejercicios = mysqli_query($conexion, "
    SELECT e.*, u.nombre AS nombre_creador
    FROM ejercicios e
    JOIN usuario u ON u.id_usuario = e.id_usuario
    ORDER BY e.tipo, e.nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ejercicios | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado">
      <div><h1>Ejercicios</h1><p><?= $es_admin
        ? "Catálogo de ejercicios usado por el generador automático de rutinas."
        : "Aporta ejercicios al catálogo compartido. Puedes editar o eliminar únicamente los que tú creaste." ?></p></div>
    </div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="panel-card">
      <h2><?= $ejercicio_editar ? "Editar ejercicio" : "Nuevo ejercicio" ?></h2>
      <form method="POST">
        <input type="hidden" name="id_ejercicio" value="<?= htmlspecialchars($ejercicio_editar["id_ejercicio"] ?? "") ?>">
        <div class="form-grid">
          <div class="campo">
            <label>Nombre</label>
            <input type="text" name="nombre" placeholder="Ej: Sentadilla con peso corporal" required
                   value="<?= htmlspecialchars($ejercicio_editar["nombre"] ?? "") ?>">
          </div>
          <div class="campo">
            <label>Tipo</label>
            <select name="tipo">
              <?php foreach (["Cardio","Fuerza","Flexibilidad","Movilidad"] as $t): ?>
                <option value="<?= $t ?>" <?= (($ejercicio_editar["tipo"] ?? "") === $t) ? "selected" : "" ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo full">
            <label>Instrucciones</label>
            <input type="text" name="instrucciones" placeholder="Cómo ejecutarlo correctamente"
                   value="<?= htmlspecialchars($ejercicio_editar["instrucciones"] ?? "") ?>">
          </div>
          <div class="campo full">
            <label>Descripción</label>
            <input type="text" name="descripcion" placeholder="Para qué sirve / músculos que trabaja"
                   value="<?= htmlspecialchars($ejercicio_editar["descripcion"] ?? "") ?>">
          </div>
        </div>
        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm"><?= $ejercicio_editar ? "Guardar cambios" : "Crear ejercicio" ?></button>
          <?php if ($ejercicio_editar): ?><a href="ejercicios.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h2>Catálogo de ejercicios</h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Nombre</th><th>Tipo</th><th>Instrucciones</th><th>Descripción</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($ejercicios) === 0): ?>
              <tr><td colspan="5" class="vacio-tabla">Aún no hay ejercicios creados.</td></tr>
            <?php else: while ($e = mysqli_fetch_assoc($ejercicios)): ?>
              <tr>
                <td><?= htmlspecialchars($e["nombre"]) ?></td>
                <td><span class="pill"><?= htmlspecialchars($e["tipo"]) ?></span></td>
                <td><?= htmlspecialchars($e["instrucciones"]) ?: "—" ?></td>
                <td><?= htmlspecialchars($e["descripcion"]) ?: "—" ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="ejercicios.php?editar=<?= $e['id_ejercicio'] ?>">Editar</a>
                  <a class="eliminar" href="ejercicios.php?eliminar=<?= $e['id_ejercicio'] ?>" onclick="return confirm('¿Eliminar este ejercicio?');">Eliminar</a>
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
