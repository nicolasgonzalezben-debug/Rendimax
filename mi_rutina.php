<?php
require_once "config.php";
exigirRol("estudiante");
$seccion_activa = "rutinas";
$mi_id = (int)$_SESSION["id_usuario"];

$stmt = mysqli_prepare($conexion, "
  SELECT * FROM rutina WHERE id_usuario = ? OR id_usuario IS NULL
  ORDER BY (id_usuario IS NULL), fecha_creacion DESC");
mysqli_stmt_bind_param($stmt, "i", $mi_id);
mysqli_stmt_execute($stmt);
$rutinas = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Rutina | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Mi Rutina de Entrenamiento</h1><p>Tu rutina asignada y las rutinas generales disponibles.</p></div></div>

    <?php if (mysqli_num_rows($rutinas) === 0): ?>
      <div class="panel-card"><p class="vacio-tabla">Tu profesor aún no te ha asignado una rutina.</p></div>
    <?php else: while ($r = mysqli_fetch_assoc($rutinas)):
        $stmt2 = mysqli_prepare($conexion, "SELECT e.nombre, e.tipo, e.instrucciones, e.descripcion, re.series, re.repeticiones
                                             FROM rutina_ejercicio re JOIN ejercicios e ON e.id_ejercicio = re.id_ejercicio
                                             WHERE re.id_rutina = ?");
        mysqli_stmt_bind_param($stmt2, "i", $r["id_rutina"]);
        mysqli_stmt_execute($stmt2);
        $ejercicios_rutina = mysqli_stmt_get_result($stmt2);
    ?>
      <div class="panel-card">
        <h2><?= htmlspecialchars($r["nombre"]) ?>
          <span class="pill"><?= htmlspecialchars($r["nivel"]) ?></span>
          <span class="pill gris"><?= htmlspecialchars($r["duracion_estimada"]) ?></span>
          <?= $r["id_usuario"] ? '<span class="pill">Solo para ti</span>' : '<span class="pill gris">General</span>' ?>
        </h2>
        <?php if (!empty($r["descripcion"])): ?>
          <div class="rutina-indicaciones">
            <strong>Cómo hacer esta rutina</strong>
            <p><?= nl2br(htmlspecialchars($r["descripcion"])) ?></p>
          </div>
        <?php endif; ?>
        <div class="tabla-wrap">
          <table class="tabla-datos">
            <thead><tr><th>Ejercicio</th><th>Tipo</th><th>Series</th><th>Repeticiones</th><th>Cómo ejecutarlo</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($ejercicios_rutina) === 0): ?>
                <tr><td colspan="5" class="vacio-tabla">Sin ejercicios asignados todavía.</td></tr>
              <?php else: while ($ej = mysqli_fetch_assoc($ejercicios_rutina)): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($ej["nombre"]) ?></strong><?= $ej["descripcion"] ? '<br><span style="font-size:11.5px;color:#9aa4b2;">' . htmlspecialchars($ej["descripcion"]) . '</span>' : "" ?></td>
                  <td><span class="pill gris"><?= htmlspecialchars($ej["tipo"]) ?></span></td>
                  <td><?= (int)$ej["series"] ?></td>
                  <td><?= htmlspecialchars($ej["repeticiones"]) ?></td>
                  <td><?= $ej["instrucciones"] ? htmlspecialchars($ej["instrucciones"]) : "—" ?></td>
                </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>
</main>
</body>
</html>
