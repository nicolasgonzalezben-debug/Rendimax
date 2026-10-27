<?php
require_once "config.php";
exigirRol("estudiante");
$seccion_activa = "racha";
$mi_id = (int)$_SESSION["id_usuario"];

$rangos = rangosRacha();

$stmt = mysqli_prepare($conexion, "SELECT * FROM racha WHERE id_usuario=? ORDER BY id_racha DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $mi_id);
mysqli_stmt_execute($stmt);
$mi_racha = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ["dias_consecutivos" => 0, "fecha_ultima" => null];

$hoy  = date("Y-m-d");
$ayer = date("Y-m-d", strtotime("-1 day"));
$fecha_ultima = $mi_racha["fecha_ultima"];


if ($fecha_ultima === null) {
    $estado = "sin_empezar";
    $dias = 0;
} elseif ($fecha_ultima === $hoy) {
    $estado = "al_dia";
    $dias = (int)$mi_racha["dias_consecutivos"];
} elseif ($fecha_ultima === $ayer) {
    $estado = "en_riesgo";
    $dias = (int)$mi_racha["dias_consecutivos"];
} else {
    $estado = "perdida";
    $dias = 0;
}

$rango_actual = $rangos[0]; $rango_siguiente = null;
foreach ($rangos as $i => $r) {
    if ($dias >= $r["meta"]) { $rango_actual = $r; $rango_siguiente = $rangos[$i + 1] ?? null; }
}
if ($rango_siguiente) {
    $rango_anterior_meta = $rango_actual["meta"];
    $porcentaje = round((($dias - $rango_anterior_meta) / max(1, $rango_siguiente["meta"] - $rango_anterior_meta)) * 100);
} else {
    $porcentaje = 100;
}

$mensajes_estado = [
    "al_dia"       => ["texto" => " Ya registraste tu progreso hoy. ¡Vuelve mañana para que la racha siga!", "clase" => "ok"],
    "en_riesgo"    => ["texto" => " Todavía no registras tu progreso de hoy. Si no lo haces antes de medianoche, tu racha se reinicia mañana.", "clase" => "alerta"],
    "perdida"      => ["texto" => " Tu racha se reinició por falta de actividad. Registra tu progreso hoy para empezar de nuevo.", "clase" => "alerta"],
    "sin_empezar"  => ["texto" => " Aún no has empezado tu racha. Registra tu progreso de hoy en Mi Progreso para arrancar.", "clase" => "ok"],
];
$msg = $mensajes_estado[$estado];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Racha | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Mi Racha</h1><p>Tu mascota evoluciona según tus días consecutivos registrando tu progreso.</p></div></div>

    <div class="mensaje <?= $msg["clase"] === "ok" ? "exito" : "error" ?>"><?= $msg["texto"] ?> <?php if ($estado !== "al_dia"): ?><a href="mi_progreso.php" style="text-decoration:underline; font-weight:600;">Registrar progreso de hoy →</a><?php endif; ?></div>

    <div class="panel-card racha-card-principal">
      <div class="racha-mascota-actual">
        <img src="img/mascotas/<?= $rango_actual['img'] ?>.png" alt="Mascota <?= $rango_actual['nombre'] ?>">
      </div>
      <div class="racha-info-actual">
        <span class="pill" style="font-size:14px; padding:8px 18px;">Rango: <?= htmlspecialchars($rango_actual["nombre"]) ?></span>
        <h2 style="margin:14px 0 6px; font-size:26px;"> <?= $dias ?> día<?= $dias === 1 ? "" : "s" ?> seguidos</h2>
        <p style="font-size:13.5px; color:var(--gris-texto);">
          <?php if ($rango_siguiente): ?>
            Te faltan <strong><?= max(0, $rango_siguiente["meta"] - $dias) ?> días</strong> para llegar a <strong><?= $rango_siguiente["nombre"] ?></strong>.
          <?php else: ?>
            ¡Llegaste al rango máximo, <strong>Invictus</strong>! 
          <?php endif; ?>
        </p>
        <div class="barra-racha">
          <div class="barra-racha-relleno" style="width:<?= $porcentaje ?>%;"></div>
        </div>
        <p style="font-size:11.5px; color:#9aa4b2; margin-top:10px;">
          <?= $fecha_ultima ? "Último progreso registrado: " . htmlspecialchars($fecha_ultima) : "Todavía no has registrado ningún progreso." ?>
        </p>
      </div>
    </div>

    <div class="panel-card">
      <h2>Todos los rangos</h2>
      <div class="rangos-fila">
        <?php foreach ($rangos as $r): $activo = $r["nombre"] === $rango_actual["nombre"]; ?>
          <div class="rango-item <?= $activo ? 'activo' : '' ?>">
            <img src="img/mascotas/<?= $r['img'] ?>.png" alt="Mascota <?= $r['nombre'] ?>">
            <span class="nombre-rango"><?= $r["nombre"] ?></span>
            <span class="meta-rango"><?= $r["meta"] ?> día<?= $r["meta"] > 1 ? "s" : "" ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="text-align:center; margin-top:20px; font-size:12.5px; color:#9aa4b2;">
        Cómo funciona: cada día que registras tu progreso en <a href="mi_progreso.php">Mi Progreso</a>, tu racha sube 1. Si dejas pasar un día sin registrar, la racha se reinicia en 0 y tu mascota vuelve a Bronce.
      </p>
    </div>
  </div>
</main>
</body>
</html>
