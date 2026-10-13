<?php
require_once "config.php";
verificarSesion();

$mi_rol = $_SESSION["rol"] ?? "";
$mi_id  = (int)$_SESSION["id_usuario"];
$seccion_activa = "dashboard";
$acceso_denegado = isset($_GET["acceso"]) && $_GET["acceso"] === "denegado";

if ($mi_rol === "administrador") {
    $total_usuarios   = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuario WHERE rol='estudiante'"))["n"] ?? 0;
    $total_docentes   = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuario WHERE rol='docente'"))["n"] ?? 0;
    $total_planes     = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM plan_alimentacion"))["n"] ?? 0;
    $total_rutinas    = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM rutina"))["n"] ?? 0;
} elseif ($mi_rol === "docente") {
    $stmt = mysqli_prepare($conexion, "
        SELECT COUNT(DISTINCT u.id_usuario) AS n
        FROM usuario u
        JOIN perfil p ON p.id_usuario = u.id_usuario
        JOIN asignacion_docente ad ON ad.grado = p.grado
        WHERE ad.id_docente = ? AND u.rol = 'estudiante'");
    mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
    $total_asignados = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))["n"] ?? 0;

    $total_grados = mysqli_fetch_assoc(
        mysqli_query($conexion, "SELECT COUNT(*) AS n FROM asignacion_docente WHERE id_docente=$mi_id")
    )["n"] ?? 0;
} else {
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM plan_alimentacion WHERE id_usuario=? OR id_usuario IS NULL");
    mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
    $total_planes = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))["n"] ?? 0;

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM rutina WHERE id_usuario=? OR id_usuario IS NULL");
    mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
    $total_rutinas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))["n"] ?? 0;

    $stmt = mysqli_prepare($conexion, "SELECT dias_consecutivos, fecha_ultima FROM racha WHERE id_usuario=? ORDER BY id_racha DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $mi_id); mysqli_stmt_execute($stmt);
    $fila_racha = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ["dias_consecutivos" => 0, "fecha_ultima" => null];

    // Mismo cálculo que mi_racha.php: si pasó más de un día sin
    // registrar progreso, la racha efectiva ya está en 0 aunque la
    // base de datos todavía no se haya actualizado.
    $hoy = date("Y-m-d"); $ayer = date("Y-m-d", strtotime("-1 day"));
    $dias_racha_efectivos = in_array($fila_racha["fecha_ultima"], [$hoy, $ayer], true) ? (int)$fila_racha["dias_consecutivos"] : 0;
    $mi_racha = ["dias_consecutivos" => $dias_racha_efectivos, "nivel_personaje" => nivelParaDias($dias_racha_efectivos)];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">

    <?php if ($acceso_denegado): ?>
      <div class="mensaje error">No tienes permisos para acceder a esa sección.</div>
    <?php endif; ?>

    <div class="panel-encabezado">
      <div>
        <h1>Hola, <?= htmlspecialchars(explode(" ", $_SESSION["nombre"])[0]) ?> 👋</h1>
        <p>
          <?php if ($mi_rol === "administrador"): ?>Este es el resumen general de RendiMAX.
          <?php elseif ($mi_rol === "docente"): ?>Este es el resumen de tus estudiantes autorizados.
          <?php else: ?>Este es tu resumen personal en RendiMAX.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="resumen-grid">
      <?php if ($mi_rol === "administrador"): ?>
        <div class="resumen-card"><div class="valor"><?= (int)$total_usuarios ?></div><div class="etiqueta">Estudiantes</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_docentes ?></div><div class="etiqueta">Docentes</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_planes ?></div><div class="etiqueta">Planes de alimentación</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_rutinas ?></div><div class="etiqueta">Rutinas de entrenamiento</div></div>
      <?php elseif ($mi_rol === "docente"): ?>
        <div class="resumen-card"><div class="valor"><?= (int)$total_asignados ?></div><div class="etiqueta">Estudiantes a tu cargo</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_grados ?></div><div class="etiqueta">Grados a tu cargo</div></div>
      <?php else: ?>
        <div class="resumen-card"><div class="valor"><?= htmlspecialchars($mi_racha["nivel_personaje"]) ?></div><div class="etiqueta">Tu rango actual</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$mi_racha["dias_consecutivos"] ?></div><div class="etiqueta">Días de racha</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_planes ?></div><div class="etiqueta">Planes disponibles</div></div>
        <div class="resumen-card"><div class="valor"><?= (int)$total_rutinas ?></div><div class="etiqueta">Rutinas disponibles</div></div>
      <?php endif; ?>
    </div>

    <h2 class="seccion-titulo" style="font-size:19px; margin-bottom:20px;">Gestionar</h2>
    <div class="modulos-grid">
      <?php if ($mi_rol === "administrador"): ?>
        <div class="modulo-card"><div class="icono">U</div><h3>Usuarios</h3><p>Gestiona cuentas y asigna el rol Estudiante o Docente.</p><a href="usuarios.php">Ir a Usuarios →</a></div>
        <div class="modulo-card"><div class="icono">📋</div><h3>Asignaciones</h3><p>Decide qué estudiantes puede supervisar cada docente.</p><a href="admin_asignaciones.php">Ir a Asignaciones →</a></div>
        <div class="modulo-card"><div class="icono">A</div><h3>Planes de Alimentación</h3><p>Genera planes automáticos o créalos manualmente.</p><a href="planes_alimentacion.php">Ir a Alimentación →</a></div>
        <div class="modulo-card"><div class="icono">R</div><h3>Rutinas de Entrenamiento</h3><p>Genera rutinas automáticas desde el catálogo de ejercicios.</p><a href="rutinas_entrenamiento.php">Ir a Rutinas →</a></div>
        <div class="modulo-card"><div class="icono">E</div><h3>Ejercicios</h3><p>Crea, edita y elimina el catálogo de ejercicios.</p><a href="ejercicios.php">Ir a Ejercicios →</a></div>
        <div class="modulo-card"><div class="icono">P</div><h3>Monitoreo de Progreso</h3><p>Consulta la evolución de todos los estudiantes (solo lectura).</p><a href="monitoreo_progreso.php">Ver monitoreo →</a></div>
      <?php elseif ($mi_rol === "docente"): ?>
        <div class="modulo-card"><div class="icono">P</div><h3>Mis Estudiantes</h3><p>Consulta el progreso de los estudiantes que tienes autorizados (solo lectura).</p><a href="monitoreo_progreso.php">Ver mis estudiantes →</a></div>
        <div class="modulo-card"><div class="icono">A</div><h3>Alimentación</h3><p>Crea planes de alimentación para tus estudiantes asignados.</p><a href="planes_alimentacion.php">Ir a Alimentación →</a></div>
        <div class="modulo-card"><div class="icono">R</div><h3>Rutinas</h3><p>Crea rutinas de entrenamiento para tus estudiantes asignados.</p><a href="rutinas_entrenamiento.php">Ir a Rutinas →</a></div>
      <?php else: ?>
        <div class="modulo-card"><div class="icono">U</div><h3>Mi Perfil</h3><p>Consulta y edita tus datos personales y tu contraseña.</p><a href="mi_perfil.php">Ir a Mi Perfil →</a></div>
        <div class="modulo-card"><div class="icono">A</div><h3>Mi Alimentación</h3><p>Consulta el plan que te asignaron.</p><a href="mi_plan.php">Ver mi plan →</a></div>
        <div class="modulo-card"><div class="icono">R</div><h3>Mi Rutina</h3><p>Consulta la rutina que te asignaron.</p><a href="mi_rutina.php">Ver mi rutina →</a></div>
        <div class="modulo-card"><div class="icono">P</div><h3>Mi Progreso</h3><p>Registra tu propia evolución de peso en el tiempo.</p><a href="mi_progreso.php">Ir a mi progreso →</a></div>
        <div class="modulo-card"><div class="icono">🔥</div><h3>Mi Racha</h3><p>Mira tu mascota, tu rango y cuánto falta para el siguiente.</p><a href="mi_racha.php">Ver mi racha →</a></div>
      <?php endif; ?>
    </div>

  </div>
</main>

</body>
</html>
