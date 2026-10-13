<?php
require_once "config.php";
exigirRol("estudiante");
$seccion_activa = "alimentacion";
$mi_id = (int)$_SESSION["id_usuario"];

$stmt = mysqli_prepare($conexion, "
  SELECT * FROM plan_alimentacion WHERE id_usuario = ? OR id_usuario IS NULL
  ORDER BY (id_usuario IS NULL), fecha_creacion DESC");
mysqli_stmt_bind_param($stmt, "i", $mi_id);
mysqli_stmt_execute($stmt);
$planes = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Plan de Alimentación | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Mi Plan de Alimentación</h1><p>Tu plan asignado y los planes generales disponibles.</p></div></div>
    <?php if (mysqli_num_rows($planes) === 0): ?>
      <div class="panel-card"><p class="vacio-tabla">Tu profesor aún no te ha asignado un plan de alimentación.</p></div>
    <?php else: while ($p = mysqli_fetch_assoc($planes)): ?>
      <div class="panel-card">
        <h2><?= htmlspecialchars($p["nombre_plan"]) ?>
          <span class="pill"><?= htmlspecialchars($p["objetivo"]) ?></span>
          <span class="pill gris"><?= (int)$p["calorias_diarias"] ?> kcal/día</span>
          <?= $p["id_usuario"] ? '<span class="pill">Solo para ti</span>' : '<span class="pill gris">General</span>' ?>
        </h2>
        <?php if (!empty($p["descripcion"])): ?>
          <div class="rutina-indicaciones">
            <strong>Qué debes comer</strong>
            <p><?= nl2br(htmlspecialchars($p["descripcion"])) ?></p>
          </div>
        <?php else: ?>
          <p class="vacio-tabla">Sin indicaciones detalladas todavía.</p>
        <?php endif; ?>
      </div>
    <?php endwhile; endif; ?>
  </div>
</main>
</body>
</html>
