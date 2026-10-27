<?php
require_once "config.php";
exigirRol("estudiante");
$seccion_activa = "progreso";
$mi_id = (int)$_SESSION["id_usuario"];

$error = ""; $exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_progreso = $_POST["id_progreso"] ?? "";
    $fecha       = $_POST["fecha"] ?? "";
    $peso        = $_POST["peso"] ?? "";
    $tiempo      = trim($_POST["tiempo_entrenamiento"] ?? "");
    $rutinas_c   = trim($_POST["rutinas_completadas"] ?? "");

    if ($fecha === "" || $peso === "") {
        $error = "La fecha y el peso son obligatorios.";
    } elseif ($id_progreso === "") {
        $stmt = mysqli_prepare($conexion, "INSERT INTO progreso (fecha, peso, tiempo_entrenamiento, rutinas_completadas, id_usuario) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sissi", $fecha, $peso, $tiempo, $rutinas_c, $mi_id);
        mysqli_stmt_execute($stmt);

        if ($fecha === date("Y-m-d")) {
            actualizarRacha($conexion, $mi_id);
        }
        $exito = "Registro de progreso creado correctamente.";
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE progreso SET fecha=?, peso=?, tiempo_entrenamiento=?, rutinas_completadas=? WHERE id_progreso=? AND id_usuario=?");
        mysqli_stmt_bind_param($stmt, "sissii", $fecha, $peso, $tiempo, $rutinas_c, $id_progreso, $mi_id);
        mysqli_stmt_execute($stmt);
        $exito = "Registro actualizado correctamente.";
    }
}

if (isset($_GET["eliminar"])) {
    $id = (int)$_GET["eliminar"];
    $stmt = mysqli_prepare($conexion, "DELETE FROM progreso WHERE id_progreso=? AND id_usuario=?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $mi_id);
    mysqli_stmt_execute($stmt);
    header("Location: mi_progreso.php?eliminado=1");
    exit;
}
if (isset($_GET["eliminado"])) { $exito = "Registro eliminado correctamente."; }

$registro_editar = null;
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM progreso WHERE id_progreso=? AND id_usuario=?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $mi_id);
    mysqli_stmt_execute($stmt);
    $registro_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$stmt = mysqli_prepare($conexion, "SELECT * FROM progreso WHERE id_usuario=? ORDER BY fecha DESC");
mysqli_stmt_bind_param($stmt, "i", $mi_id);
mysqli_stmt_execute($stmt);
$registros = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Progreso | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Mi Progreso</h1><p>Registra tu propia evolución. Tu profesor puede verlo, pero no editarlo por ti.  Registrar tu progreso de <strong>hoy</strong> suma un día a tu racha — mira <a href="mi_racha.php">Mi Racha</a>.</p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="panel-card">
      <h2><?= $registro_editar ? "Editar registro" : "Nuevo registro" ?></h2>
      <form method="POST">
        <input type="hidden" name="id_progreso" value="<?= htmlspecialchars($registro_editar["id_progreso"] ?? "") ?>">
        <div class="form-grid">
          <div class="campo"><label>Fecha</label>
            <input type="date" name="fecha" required value="<?= htmlspecialchars($registro_editar["fecha"] ?? date("Y-m-d")) ?>"></div>
          <div class="campo"><label>Peso (kg)</label>
            <input type="number" name="peso" required value="<?= htmlspecialchars($registro_editar["peso"] ?? "") ?>"></div>
          <div class="campo"><label>Tiempo de entrenamiento</label>
            <input type="text" name="tiempo_entrenamiento" placeholder="Ej: 45 min" value="<?= htmlspecialchars($registro_editar["tiempo_entrenamiento"] ?? "") ?>"></div>
          <div class="campo"><label>Rutinas completadas</label>
            <input type="text" name="rutinas_completadas" placeholder="Ej: Fuerza tren superior" value="<?= htmlspecialchars($registro_editar["rutinas_completadas"] ?? "") ?>"></div>
        </div>
        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm"><?= $registro_editar ? "Guardar cambios" : "Crear registro" ?></button>
          <?php if ($registro_editar): ?><a href="mi_progreso.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h2>Mi historial</h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Fecha</th><th>Peso</th><th>Tiempo</th><th>Rutinas completadas</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($registros) === 0): ?>
              <tr><td colspan="5" class="vacio-tabla">Aún no has registrado tu progreso.</td></tr>
            <?php else: while ($r = mysqli_fetch_assoc($registros)): ?>
              <tr>
                <td><?= htmlspecialchars($r["fecha"]) ?></td>
                <td><?= htmlspecialchars($r["peso"]) ?> kg</td>
                <td><?= htmlspecialchars($r["tiempo_entrenamiento"]) ?: "—" ?></td>
                <td><?= htmlspecialchars($r["rutinas_completadas"]) ?: "—" ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="mi_progreso.php?editar=<?= $r['id_progreso'] ?>">Editar</a>
                  <a class="eliminar" href="mi_progreso.php?eliminar=<?= $r['id_progreso'] ?>" onclick="return confirm('¿Eliminar este registro?');">Eliminar</a>
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
