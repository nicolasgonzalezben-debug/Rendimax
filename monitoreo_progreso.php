<?php
require_once "config.php";
exigirRol(["docente", "administrador"]);
$seccion_activa = "progreso";

$mi_id = (int)$_SESSION["id_usuario"];
$id_ver = isset($_GET["ver"]) ? (int)$_GET["ver"] : null;


if (esAdministrador()) {
    $resumen = mysqli_query($conexion, "
      SELECT u.id_usuario, u.nombre, r.dias_consecutivos, r.nivel_personaje,
        (SELECT COUNT(*) FROM progreso pr WHERE pr.id_usuario = u.id_usuario) AS total_registros,
        (SELECT pr2.fecha FROM progreso pr2 WHERE pr2.id_usuario = u.id_usuario ORDER BY pr2.fecha DESC LIMIT 1) AS ultima_fecha,
        (SELECT pr3.peso FROM progreso pr3 WHERE pr3.id_usuario = u.id_usuario ORDER BY pr3.fecha DESC LIMIT 1) AS ultimo_peso
      FROM usuario u
      LEFT JOIN racha r ON r.id_usuario = u.id_usuario
      WHERE u.rol = 'estudiante'
      ORDER BY u.nombre");
} else {
    $stmt = mysqli_prepare($conexion, "
      SELECT DISTINCT u.id_usuario, u.nombre, p.grado, r.dias_consecutivos, r.nivel_personaje,
        (SELECT COUNT(*) FROM progreso pr WHERE pr.id_usuario = u.id_usuario) AS total_registros,
        (SELECT pr2.fecha FROM progreso pr2 WHERE pr2.id_usuario = u.id_usuario ORDER BY pr2.fecha DESC LIMIT 1) AS ultima_fecha,
        (SELECT pr3.peso FROM progreso pr3 WHERE pr3.id_usuario = u.id_usuario ORDER BY pr3.fecha DESC LIMIT 1) AS ultimo_peso
      FROM usuario u
      JOIN perfil p ON p.id_usuario = u.id_usuario
      JOIN asignacion_docente ad ON ad.grado = p.grado AND ad.id_docente = ?
      LEFT JOIN racha r ON r.id_usuario = u.id_usuario
      WHERE u.rol = 'estudiante'
      ORDER BY u.nombre");
    mysqli_stmt_bind_param($stmt, "i", $mi_id);
    mysqli_stmt_execute($stmt);
    $resumen = mysqli_stmt_get_result($stmt);
}

$detalle_nombre = ""; $detalle = null;
if ($id_ver) {

    $autorizado = esAdministrador() || esDocenteDeEstudiante($conexion, $mi_id, $id_ver);

    if ($autorizado) {
        $stmt = mysqli_prepare($conexion, "SELECT nombre FROM usuario WHERE id_usuario=?");
        mysqli_stmt_bind_param($stmt, "i", $id_ver);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $detalle_nombre = $fila["nombre"] ?? "";

        $stmt2 = mysqli_prepare($conexion, "SELECT * FROM progreso WHERE id_usuario=? ORDER BY fecha DESC");
        mysqli_stmt_bind_param($stmt2, "i", $id_ver);
        mysqli_stmt_execute($stmt2);
        $detalle = mysqli_stmt_get_result($stmt2);
    } else {
        $id_ver = null; 
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitoreo de Progreso | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado">
      <div><h1>Monitoreo de Progreso</h1>
      <p>Vista de solo lectura<?= esDocente() ? " — de todos los estudiantes de los grados que tienes a cargo" : "" ?>: aquí no se crea, edita ni elimina nada.</p></div>
    </div>

    <div class="panel-card">
      <h2>Estudiantes</h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Nombre</th><?= esDocente() ? "<th>Grado</th>" : "" ?><th>Rango</th><th>Racha</th><th>Registros</th><th>Último peso</th><th>Última fecha</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($resumen) === 0): ?>
              <tr><td colspan="<?= esDocente() ? 8 : 7 ?>" class="vacio-tabla"><?= esDocente() ? "Aún no tienes grados asignados. Pídele a un administrador que te autorice desde Asignaciones." : "Aún no hay estudiantes registrados." ?></td></tr>
            <?php else: while ($u = mysqli_fetch_assoc($resumen)): ?>
              <tr>
                <td><?= htmlspecialchars($u["nombre"]) ?></td>
                <?php if (esDocente()): ?><td><?= (int)$u["grado"] ?>°</td><?php endif; ?>
                <td><span class="pill logro"><?= htmlspecialchars($u["nivel_personaje"] ?? "Bronce") ?></span></td>
                <td><?= (int)($u["dias_consecutivos"] ?? 0) ?> días</td>
                <td><?= (int)$u["total_registros"] ?></td>
                <td><?= $u["ultimo_peso"] ? htmlspecialchars($u["ultimo_peso"]) . " kg" : "—" ?></td>
                <td><?= htmlspecialchars($u["ultima_fecha"] ?? "—") ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="monitoreo_progreso.php?ver=<?= $u['id_usuario'] ?>">Ver historial</a>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($id_ver): ?>
    <div class="panel-card">
      <h2>Historial de <?= htmlspecialchars($detalle_nombre) ?></h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Fecha</th><th>Peso</th><th>Tiempo de entrenamiento</th><th>Rutinas completadas</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($detalle) === 0): ?>
              <tr><td colspan="4" class="vacio-tabla">Este estudiante aún no ha registrado su progreso.</td></tr>
            <?php else: while ($d = mysqli_fetch_assoc($detalle)): ?>
              <tr>
                <td><?= htmlspecialchars($d["fecha"]) ?></td>
                <td><?= htmlspecialchars($d["peso"]) ?> kg</td>
                <td><?= htmlspecialchars($d["tiempo_entrenamiento"]) ?: "—" ?></td>
                <td><?= htmlspecialchars($d["rutinas_completadas"]) ?: "—" ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
