<?php
require_once "config.php";
exigirRol(["docente", "administrador"]);
$seccion_activa = "rutinas";

$mi_id    = (int)$_SESSION["id_usuario"];
$es_admin = esAdministrador();

$error = ""; $exito = "";
$sugeridos = []; 

function tipos_para_objetivo($objetivo) {
    if ($objetivo === "Bajar de peso")   return ["Cardio","Fuerza"];
    if ($objetivo === "Ganar músculo")   return ["Fuerza"];
    return ["Cardio","Fuerza","Flexibilidad","Movilidad"]; // Mantenimiento
}
function series_reps_por_nivel($nivel) {
    if ($nivel === "Principiante") return [2, "10"];
    if ($nivel === "Avanzado")     return [4, "15"];
    return [3, "12"]; // Intermedio
}


function descripcion_rutina_detallada($nivel, $objetivo, $series, $reps, $cantidad_ejercicios) {
    $descanso_series = $nivel === "Avanzado" ? "45-60" : ($nivel === "Intermedio" ? "60-90" : "90-120");
    $frecuencia = [
        "Bajar de peso"  => "4 a 5 veces por semana, combinando esta rutina con caminatas o cardio suave los días de descanso activo",
        "Ganar músculo"  => "3 a 4 veces por semana, dejando al menos 1 día de descanso entre sesiones que trabajen el mismo grupo muscular",
        "Mantenimiento"  => "3 veces por semana, alternando con descanso o actividad ligera",
    ][$objetivo] ?? "3 a 4 veces por semana";

    return "Esta rutina de nivel $nivel está pensada para $objetivo y tiene $cantidad_ejercicios ejercicios.\n\n"
        . "Cómo hacerla:\n"
        . "1. Calienta 5-8 minutos antes de empezar (trote suave, saltos, movilidad articular) para evitar lesiones.\n"
        . "2. Haz cada ejercicio marcado en el orden de la lista: $series series de $reps repeticiones.\n"
        . "3. Descansa $descanso_series segundos entre cada serie, y 2-3 minutos al cambiar de ejercicio si sientes fatiga.\n"
        . "4. Cuida la técnica antes que el peso o la velocidad — revisa las instrucciones de cada ejercicio más abajo.\n"
        . "5. Termina con 5 minutos de estiramiento de los músculos trabajados.\n\n"
        . "Frecuencia recomendada: $frecuencia. Registra cómo te sentiste en Mi Progreso después de cada sesión.";
}


if ($es_admin) {
    $estudiantes = mysqli_query($conexion, "SELECT id_usuario, nombre FROM usuario WHERE rol='estudiante' ORDER BY nombre");
} else {
    $estudiantes = estudiantesDeDocente($conexion, $mi_id);
}
$ids_permitidos = [];
mysqli_data_seek($estudiantes, 0);
while ($e = mysqli_fetch_assoc($estudiantes)) { $ids_permitidos[] = (int)$e["id_usuario"]; }
mysqli_data_seek($estudiantes, 0);


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generar"])) {
    $nivel_gen    = $_POST["nivel"] ?? "Principiante";
    $objetivo_gen = $_POST["objetivo"] ?? "Mantenimiento";
    $cantidad     = max(3, min(6, (int)($_POST["cantidad"] ?? 5)));
    $tipos = tipos_para_objetivo($objetivo_gen);
    $placeholders = implode(",", array_fill(0, count($tipos), "?"));
    $sql = "SELECT id_ejercicio FROM ejercicios WHERE tipo IN ($placeholders) ORDER BY RAND() LIMIT ?";
    $stmt = mysqli_prepare($conexion, $sql);
    $tipos_ref = $tipos; $tipos_ref[] = $cantidad;
    $types = str_repeat("s", count($tipos)) . "i";
    mysqli_stmt_bind_param($stmt, $types, ...$tipos_ref);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $sugeridos[] = (int)$row["id_ejercicio"]; }

    $duracion_min = count($sugeridos) * 8;
    [$series_gen, $reps_gen] = series_reps_por_nivel($nivel_gen);
    $_POST["nombre_rutina"]      = $_POST["nombre_rutina"] ?? ucfirst(strtolower($objetivo_gen)) . " - " . $nivel_gen;
    $_POST["nivel"]              = $nivel_gen;
    $_POST["objetivo"]           = $objetivo_gen;
    $_POST["duracion_estimada"]  = $duracion_min . " min";
    $_POST["descripcion_sugerida"] = descripcion_rutina_detallada($nivel_gen, $objetivo_gen, $series_gen, $reps_gen, count($sugeridos));
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["generar"])) {
    $id_rutina     = $_POST["id_rutina"] ?? "";
    $nombre_rutina = trim($_POST["nombre_rutina"] ?? "");
    $nivel         = $_POST["nivel"] ?? "Principiante";
    $objetivo      = $_POST["objetivo"] ?? null;
    $duracion      = trim($_POST["duracion_estimada"] ?? "");
    $descripcion   = trim($_POST["descripcion"] ?? "");
    $id_usuario    = $_POST["id_usuario"] ?? "";
    $id_usuario_db = $id_usuario === "" ? null : (int)$id_usuario;
    $auto          = isset($_POST["fue_generado"]) ? 1 : 0;
    $ejercicios_marcados = $_POST["ejercicios"] ?? [];

    
    $propietario_valido = $es_admin || ($id_usuario_db !== null && in_array($id_usuario_db, $ids_permitidos, true));

    if ($nombre_rutina === "" || $duracion === "") {
        $error = "El nombre de la rutina y la duración son obligatorios.";
    } elseif (!$propietario_valido) {
        $error = "Solo puedes crear o editar rutinas para tus estudiantes asignados.";
    } else {
        [$series_def, $reps_def] = series_reps_por_nivel($nivel);
        
        if ($descripcion === "") {
            $descripcion = descripcion_rutina_detallada($nivel, $objetivo ?? "Mantenimiento", $series_def, $reps_def, count($ejercicios_marcados));
        }
        if ($id_rutina === "") {
            $sql = "INSERT INTO rutina (nombre, nivel, tipo, objetivo, duracion_estimada, descripcion, generado_automaticamente, id_usuario) VALUES (?,?,?,?,?,?,?,?)";
            $stmt = mysqli_prepare($conexion, $sql);
            $tipo_general = $objetivo === "Ganar músculo" ? "Fuerza" : ($objetivo === "Bajar de peso" ? "Cardio" : "Movilidad");
            mysqli_stmt_bind_param($stmt, "ssssssii", $nombre_rutina, $nivel, $tipo_general, $objetivo, $duracion, $descripcion, $auto, $id_usuario_db);
            mysqli_stmt_execute($stmt);
            $nueva_id = mysqli_insert_id($conexion);
            foreach ($ejercicios_marcados as $id_ej) {
                $stmt2 = mysqli_prepare($conexion, "INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, series, repeticiones) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($stmt2, "iiis", $nueva_id, $id_ej, $series_def, $reps_def);
                mysqli_stmt_execute($stmt2);
            }
            $exito = "Rutina creada correctamente" . (count($ejercicios_marcados) ? " con " . count($ejercicios_marcados) . " ejercicios." : ".");
        } else {
            if ($es_admin) {
                $sql = "UPDATE rutina SET nombre=?, nivel=?, objetivo=?, duracion_estimada=?, descripcion=?, id_usuario=? WHERE id_rutina=?";
                $stmt = mysqli_prepare($conexion, $sql);
                mysqli_stmt_bind_param($stmt, "sssssii", $nombre_rutina, $nivel, $objetivo, $duracion, $descripcion, $id_usuario_db, $id_rutina);
                mysqli_stmt_execute($stmt);
            } else {
                $placeholders = implode(",", array_fill(0, count($ids_permitidos), "?"));
                $sql = "UPDATE rutina SET nombre=?, nivel=?, objetivo=?, duracion_estimada=?, descripcion=?, id_usuario=?
                        WHERE id_rutina=? AND id_usuario IN ($placeholders)";
                $stmt = mysqli_prepare($conexion, $sql);
                $tipos = "sssssii" . str_repeat("i", count($ids_permitidos));
                $valores = array_merge([$nombre_rutina, $nivel, $objetivo, $duracion, $descripcion, $id_usuario_db, $id_rutina], $ids_permitidos);
                mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
                mysqli_stmt_execute($stmt);
            }
            mysqli_query($conexion, "DELETE FROM rutina_ejercicio WHERE id_rutina=" . (int)$id_rutina);
            foreach ($ejercicios_marcados as $id_ej) {
                $stmt2 = mysqli_prepare($conexion, "INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, series, repeticiones) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($stmt2, "iiis", $id_rutina, $id_ej, $series_def, $reps_def);
                mysqli_stmt_execute($stmt2);
            }
            $exito = "Rutina actualizada correctamente.";
        }
    }
}

if (isset($_GET["eliminar"])) {
    $id = (int)$_GET["eliminar"];
    if ($es_admin) {
        $stmt = mysqli_prepare($conexion, "DELETE FROM rutina WHERE id_rutina = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
    } else {
        $placeholders = implode(",", array_fill(0, count($ids_permitidos), "?"));
        $stmt = mysqli_prepare($conexion, "DELETE FROM rutina WHERE id_rutina = ? AND id_usuario IN ($placeholders)");
        $tipos = "i" . str_repeat("i", count($ids_permitidos));
        $valores = array_merge([$id], $ids_permitidos);
        mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
    }
    mysqli_stmt_execute($stmt);
    header("Location: rutinas_entrenamiento.php?eliminado=1");
    exit;
}
if (isset($_GET["eliminado"])) { $exito = "Rutina eliminada correctamente."; }

$rutina_editar = null; $ejercicios_de_rutina = [];
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM rutina WHERE id_rutina = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $rutina_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($rutina_editar && !$es_admin && !in_array((int)$rutina_editar["id_usuario"], $ids_permitidos, true)) {
        $rutina_editar = null;
        $error = "Esa rutina no pertenece a ninguno de tus estudiantes asignados.";
    }

    if ($rutina_editar) {
        $stmt2 = mysqli_prepare($conexion, "SELECT id_ejercicio FROM rutina_ejercicio WHERE id_rutina=?");
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($r = mysqli_fetch_assoc($res2)) { $ejercicios_de_rutina[] = (int)$r["id_ejercicio"]; }
    }
}
$preseleccionados = $sugeridos ?: $ejercicios_de_rutina;

$todos_ejercicios = mysqli_query($conexion, "SELECT * FROM ejercicios ORDER BY tipo, nombre");

if ($es_admin) {
    $rutinas = mysqli_query($conexion, "
      SELECT r.*, u.nombre AS nombre_estudiante,
        (SELECT COUNT(*) FROM rutina_ejercicio re WHERE re.id_rutina = r.id_rutina) AS total_ejercicios
      FROM rutina r
      LEFT JOIN usuario u ON u.id_usuario = r.id_usuario
      ORDER BY r.fecha_creacion DESC");
} else {
    $placeholders = implode(",", array_fill(0, max(1, count($ids_permitidos)), "?"));
    $ids_para_in = $ids_permitidos ?: [0];
    $stmt = mysqli_prepare($conexion, "
      SELECT r.*, u.nombre AS nombre_estudiante,
        (SELECT COUNT(*) FROM rutina_ejercicio re WHERE re.id_rutina = r.id_rutina) AS total_ejercicios
      FROM rutina r
      JOIN usuario u ON u.id_usuario = r.id_usuario
      WHERE r.id_usuario IN ($placeholders)
      ORDER BY r.fecha_creacion DESC");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids_para_in)), ...$ids_para_in);
    mysqli_stmt_execute($stmt);
    $rutinas = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rutinas de Entrenamiento | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Rutinas de Entrenamiento</h1>
      <p><?= $es_admin ? "Genera una rutina automática desde el catálogo de ejercicios, o créala manualmente." : "Crea rutinas para tus estudiantes asignados." ?></p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if (!$es_admin && empty($ids_permitidos)): ?>
      <div class="mensaje error">Todavía no tienes grados asignados. Pídele a un administrador que te autorice desde Asignaciones.</div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($todos_ejercicios) === 0): ?>
      <div class="mensaje error">Aún no hay ejercicios en el catálogo. El generador los necesita para armar rutinas — pídele a un administrador que cree algunos en Ejercicios.</div>
    <?php endif; ?>

    <div class="panel-card generador-card">
      <h2>🪄 Generador automático</h2>
      <p style="font-size:13px; margin-bottom:16px;">Elige nivel, objetivo y cuántos ejercicios quieres: seleccionamos ejercicios del catálogo que encajen y calculamos la duración. Puedes ajustar todo antes de guardar.</p>
      <form method="POST">
        <input type="hidden" name="generar" value="1">
        <div class="form-grid" style="margin-bottom:10px;">
          <div class="campo"><label>Nivel</label>
            <select name="nivel">
              <?php foreach (["Principiante","Intermedio","Avanzado"] as $n): ?>
                <option value="<?= $n ?>" <?= (($_POST["nivel"] ?? "") === $n) ? "selected" : "" ?>><?= $n ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo"><label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= (($_POST["objetivo"] ?? "") === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo"><label>Cantidad de ejercicios</label>
            <input type="number" name="cantidad" min="3" max="6" value="5"></div>
        </div>
        <button type="submit" class="btn btn-secundario btn-sm">Generar sugerencia →</button>
      </form>
    </div>

    <div class="panel-card">
      <h2><?= $rutina_editar ? "Editar rutina" : "Nueva rutina de entrenamiento" ?></h2>
      <form method="POST">
        <input type="hidden" name="id_rutina" value="<?= htmlspecialchars($rutina_editar["id_rutina"] ?? "") ?>">
        <?php if ($sugeridos): ?><input type="hidden" name="fue_generado" value="1"><?php endif; ?>
        <div class="form-grid">
          <div class="campo"><label>Nombre de la rutina</label>
            <input type="text" name="nombre_rutina" required
                   value="<?= htmlspecialchars($rutina_editar["nombre"] ?? $_POST["nombre_rutina"] ?? "") ?>"></div>
          <div class="campo"><label>Nivel</label>
            <select name="nivel">
              <?php foreach (["Principiante","Intermedio","Avanzado"] as $n): ?>
                <option value="<?= $n ?>" <?= (($rutina_editar["nivel"] ?? ($_POST["nivel"] ?? "")) === $n) ? "selected" : "" ?>><?= $n ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo"><label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= (($rutina_editar["objetivo"] ?? ($_POST["objetivo"] ?? "")) === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="campo"><label>Duración estimada</label>
            <input type="text" name="duracion_estimada" placeholder="Ej: 40 min" required
                   value="<?= htmlspecialchars($rutina_editar["duracion_estimada"] ?? $_POST["duracion_estimada"] ?? "") ?>"></div>
          <div class="campo"><label>Estudiante<?= $es_admin ? " (opcional)" : "" ?></label>
            <select name="id_usuario" <?= $es_admin ? "" : "required" ?>>
              <?php if ($es_admin): ?><option value="">General - disponible para todos</option><?php else: ?>
                <option value="" disabled <?= !$rutina_editar ? "selected" : "" ?>>Selecciona un estudiante</option>
              <?php endif; ?>
              <?php mysqli_data_seek($estudiantes, 0); while ($e = mysqli_fetch_assoc($estudiantes)): ?>
                <option value="<?= $e['id_usuario'] ?>" <?= ((int)($rutina_editar["id_usuario"] ?? 0) === (int)$e['id_usuario']) ? "selected" : "" ?>>
                  <?= htmlspecialchars($e['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>
            <?php if (!$es_admin): ?><small class="ayuda-campo">Solo puedes elegir entre tus estudiantes asignados.</small><?php endif; ?>
          </div>
          <div class="campo full"><label>Descripción</label>
            <textarea name="descripcion" placeholder="Indicaciones generales..."><?= htmlspecialchars($rutina_editar["descripcion"] ?? ($_POST["descripcion_sugerida"] ?? "")) ?></textarea></div>

          <div class="campo full">
            <label>Ejercicios incluidos <?= $sugeridos ? "(preseleccionados por el generador, puedes cambiarlos)" : "" ?></label>
            <div class="lista-checks">
              <?php mysqli_data_seek($todos_ejercicios, 0); while ($ej = mysqli_fetch_assoc($todos_ejercicios)): ?>
                <label class="check-ejercicio">
                  <input type="checkbox" name="ejercicios[]" value="<?= $ej['id_ejercicio'] ?>"
                    <?= in_array((int)$ej['id_ejercicio'], $preseleccionados, true) ? "checked" : "" ?>>
                  <?= htmlspecialchars($ej['nombre']) ?> <span class="pill gris"><?= htmlspecialchars($ej['tipo']) ?></span>
                </label>
              <?php endwhile; ?>
            </div>
          </div>
        </div>
        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm"><?= $rutina_editar ? "Guardar cambios" : "Crear rutina" ?></button>
          <?php if ($rutina_editar): ?><a href="rutinas_entrenamiento.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h2><?= $es_admin ? "Rutinas creadas" : "Rutinas de tus estudiantes" ?></h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Nombre</th><th>Nivel</th><th>Duración</th><th>Ejercicios</th><th>Asignada a</th><th>Origen</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($rutinas) === 0): ?>
              <tr><td colspan="7" class="vacio-tabla">Aún no hay rutinas.</td></tr>
            <?php else: while ($r = mysqli_fetch_assoc($rutinas)): ?>
              <tr>
                <td><?= htmlspecialchars($r["nombre"]) ?></td>
                <td><span class="pill"><?= htmlspecialchars($r["nivel"]) ?></span></td>
                <td><?= htmlspecialchars($r["duracion_estimada"]) ?></td>
                <td><?= (int)$r["total_ejercicios"] ?></td>
                <td><?= $r["id_usuario"] ? htmlspecialchars($r["nombre_estudiante"]) : '<span class="pill gris">General</span>' ?></td>
                <td><?= $r["generado_automaticamente"] ? '<span class="pill gris">🪄 Auto</span>' : '<span class="pill gris">Manual</span>' ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="rutinas_entrenamiento.php?editar=<?= $r['id_rutina'] ?>">Editar</a>
                  <a class="eliminar" href="rutinas_entrenamiento.php?eliminar=<?= $r['id_rutina'] ?>" onclick="return confirm('¿Eliminar esta rutina?');">Eliminar</a>
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
