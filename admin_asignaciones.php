<?php
require_once "config.php";
exigirRol("administrador");
$seccion_activa = "asignaciones";

$error = ""; $exito = "";
$GRADOS = [6 => "Sexto", 7 => "Séptimo", 8 => "Octavo", 9 => "Noveno", 10 => "Décimo", 11 => "Once"];

// -----------------------------------------------------------------
// Guardar asignación: un docente + una lista de grados (checkboxes).
// Se reemplaza TODO lo que tenía asignado ese docente por la lista
// nueva que llegó marcada (así se puede agregar y quitar grados
// desde el mismo formulario, sin una pantalla aparte para "quitar").
// -----------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "asignar") {
    $id_docente = (int)($_POST["id_docente"] ?? 0);
    $grados_marcados = $_POST["grados"] ?? [];
    $grados_validos = array_values(array_intersect(array_map("intval", $grados_marcados), array_keys($GRADOS)));

    // Verificamos en el servidor que ese id realmente sea un docente
    // (nunca confiamos en lo que venga del <select> del navegador).
    $verifica = mysqli_prepare($conexion, "SELECT id_usuario FROM usuario WHERE id_usuario=? AND rol='docente'");
    mysqli_stmt_bind_param($verifica, "i", $id_docente);
    mysqli_stmt_execute($verifica);
    mysqli_stmt_store_result($verifica);

    if ($id_docente <= 0 || mysqli_stmt_num_rows($verifica) === 0) {
        $error = "Elige un docente válido.";
    } else {
        mysqli_begin_transaction($conexion);
        $borrar = mysqli_prepare($conexion, "DELETE FROM asignacion_docente WHERE id_docente=?");
        mysqli_stmt_bind_param($borrar, "i", $id_docente);
        mysqli_stmt_execute($borrar);

        foreach ($grados_validos as $g) {
            $stmt = mysqli_prepare($conexion, "INSERT INTO asignacion_docente (id_docente, grado) VALUES (?,?)");
            mysqli_stmt_bind_param($stmt, "ii", $id_docente, $g);
            mysqli_stmt_execute($stmt);
        }
        mysqli_commit($conexion);
        $exito = empty($grados_validos)
            ? "Se quitaron todos los grados asignados a ese docente."
            : "Asignación guardada: " . count($grados_validos) . " grado(s).";
    }
}

$docentes = mysqli_query($conexion, "SELECT id_usuario, nombre FROM usuario WHERE rol='docente' ORDER BY nombre");

// Grados ya asignados a cada docente, para pintar los checkboxes y la tabla resumen
$mapa_asignaciones = []; // id_docente => [grado, grado, ...]
$res = mysqli_query($conexion, "SELECT id_docente, grado FROM asignacion_docente ORDER BY grado");
while ($fila = mysqli_fetch_assoc($res)) {
    $mapa_asignaciones[$fila["id_docente"]][] = (int)$fila["grado"];
}

// Cuántos estudiantes hay realmente en cada grado (para que el admin vea el impacto real)
$conteo_por_grado = array_fill_keys(array_keys($GRADOS), 0);
$res2 = mysqli_query($conexion, "SELECT grado, COUNT(*) AS n FROM perfil p JOIN usuario u ON u.id_usuario=p.id_usuario WHERE u.rol='estudiante' AND grado IS NOT NULL GROUP BY grado");
while ($fila = mysqli_fetch_assoc($res2)) { $conteo_por_grado[(int)$fila["grado"]] = (int)$fila["n"]; }

$docente_editar = isset($_GET["editar"]) ? (int)$_GET["editar"] : null;
$grados_del_editado = $docente_editar ? ($mapa_asignaciones[$docente_editar] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asignaciones Docente-Grado | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Asignaciones</h1><p>Decide qué grados tiene a cargo cada docente. Un docente queda automáticamente a cargo de todos los estudiantes de los grados que le marques (por ejemplo, un docente de octavo a décimo, u otro solo de los onces).</p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if (mysqli_num_rows($docentes) === 0): ?>
      <div class="mensaje error">Todavía no hay ningún usuario con rol Docente. Crea uno primero en <a href="usuarios.php" style="text-decoration:underline;">Usuarios</a>.</div>
    <?php endif; ?>

    <div class="panel-card">
      <h2><?= $docente_editar ? "Editar grados asignados" : "Asignar grados a un docente" ?></h2>
      <form method="POST">
        <input type="hidden" name="accion" value="asignar">
        <div class="form-grid" style="margin-bottom:6px;">
          <div class="campo">
            <label>Docente</label>
            <select name="id_docente" id="select-docente" required onchange="window.location='admin_asignaciones.php?editar='+this.value">
              <option value="" disabled <?= !$docente_editar ? "selected" : "" ?>>Selecciona un docente</option>
              <?php mysqli_data_seek($docentes, 0); while ($d = mysqli_fetch_assoc($docentes)): ?>
                <option value="<?= $d['id_usuario'] ?>" <?= $docente_editar === (int)$d['id_usuario'] ? "selected" : "" ?>><?= htmlspecialchars($d['nombre']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="campo full">
          <label>Grados a cargo</label>
          <div class="lista-checks">
            <?php foreach ($GRADOS as $num => $etq): ?>
              <label class="check-ejercicio">
                <input type="checkbox" name="grados[]" value="<?= $num ?>" <?= in_array($num, $grados_del_editado, true) ? "checked" : "" ?>>
                <?= $etq ?> <span class="pill gris"><?= $conteo_por_grado[$num] ?> estudiante(s)</span>
              </label>
            <?php endforeach; ?>
          </div>
          <small class="ayuda-campo">Marca uno o varios grados. Al guardar, esta lista reemplaza por completo los grados que tenía antes ese docente.</small>
        </div>

        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm">Guardar asignación</button>
          <?php if ($docente_editar): ?><a href="admin_asignaciones.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h2>Asignaciones activas</h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Docente</th><th>Grados a cargo</th><th>Estudiantes a cargo</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php
              mysqli_data_seek($docentes, 0);
              $hay_filas = false;
              while ($d = mysqli_fetch_assoc($docentes)):
                $hay_filas = true;
                $grados_d = $mapa_asignaciones[$d["id_usuario"]] ?? [];
                sort($grados_d);
                $total_estudiantes = 0;
foreach ($grados_d as $g) {
    $total_estudiantes += $conteo_por_grado[$g] ?? 0;
}            ?>
              <tr>
                <td><?= htmlspecialchars($d["nombre"]) ?></td>
                <td><?php if (empty($grados_d)): ?><span class="pill gris">Sin grados asignados</span>
<?php else: foreach ($grados_d as $g): ?>
    <span class="pill"><?= $GRADOS[$g] ?? $g ?></span>
<?php endforeach; endif; ?></td>
                <td><?= $total_estudiantes ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="admin_asignaciones.php?editar=<?= $d['id_usuario'] ?>">Editar</a>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$hay_filas): ?>
              <tr><td colspan="4" class="vacio-tabla">Aún no hay docentes registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
