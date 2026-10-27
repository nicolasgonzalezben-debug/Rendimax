<?php
require_once "config.php";
exigirRol(["docente", "administrador"]);
$seccion_activa = "alimentacion";

$mi_id    = (int)$_SESSION["id_usuario"];
$es_admin = esAdministrador(); 
$error = ""; $exito = "";
$sugerido_calorias = null; $sugerido_descripcion = "";


if ($es_admin) {
    $estudiantes = mysqli_query($conexion, "SELECT id_usuario, nombre FROM usuario WHERE rol='estudiante' ORDER BY nombre");
} else {
    $estudiantes = estudiantesDeDocente($conexion, $mi_id);
}
$ids_permitidos = [];
mysqli_data_seek($estudiantes, 0);
while ($e = mysqli_fetch_assoc($estudiantes)) { $ids_permitidos[] = (int)$e["id_usuario"]; }
mysqli_data_seek($estudiantes, 0);


function generar_plan($sexo, $edad, $estatura, $peso, $objetivo) {
    $edad = (int)$edad; $estatura = (float)$estatura; $peso = (float)$peso;
    if ($sexo === "Masculino") {
        $bmr = 10*$peso + 6.25*$estatura - 5*$edad + 5;
    } elseif ($sexo === "Femenino") {
        $bmr = 10*$peso + 6.25*$estatura - 5*$edad - 161;
    } else {
        $bmr = 10*$peso + 6.25*$estatura - 5*$edad - 78; 
    }
    $tdee = $bmr * 1.55; 

    $plantillas = [
        "Bajar de peso" => [
            "ajuste"       => -500,
            "proteina_g_kg"=> 2.0,
            "grasa_pct"    => 0.25,
            "resumen"      => "Déficit calórico moderado y alta proteína para bajar de peso cuidando la masa muscular.",
            "comidas"      => [
                "Desayuno"       => "Proteína (huevo, yogur griego o kumis) + fruta entera + una porción pequeña de carbohidrato (avena o pan integral). Evita jugos y bebidas azucaradas.",
                "Media mañana"   => "Una fruta o un puñado de frutos secos, para no llegar con mucha hambre al almuerzo.",
                "Almuerzo"       => "Mitad del plato en vegetales, un cuarto en proteína magra (pollo, pescado, carne magra, lentejas) y un cuarto en carbohidrato (arroz, papa o yuca) en porción controlada.",
                "Tarde"          => "Proteína ligera (yogur, queso bajo en grasa) + vegetales crudos.",
                "Cena"           => "Similar al almuerzo pero con una porción de carbohidrato más pequeña, al menos 2 horas antes de dormir.",
            ],
        ],
        "Ganar músculo" => [
            "ajuste"       => 350,
            "proteina_g_kg"=> 1.8,
            "grasa_pct"    => 0.25,
            "resumen"      => "Superávit calórico moderado y alta proteína para ganar masa muscular con el entrenamiento.",
            "comidas"      => [
                "Desayuno"       => "Proteína + carbohidrato complejo (avena, pan integral, arepa) + fruta. No te saltes esta comida.",
                "Media mañana"   => "Batido de proteína o fruta con frutos secos — ayuda a llegar a las calorías del día.",
                "Almuerzo"       => "Porción generosa de proteína (pollo, carne, pescado, huevo) + carbohidrato (arroz, pasta, papa) + vegetales.",
                "Pre-entreno"    => "Carbohidrato de fácil digestión 30-60 min antes de entrenar (fruta, pan) para tener energía.",
                "Post-entreno"   => "Proteína + carbohidrato dentro de la hora después de entrenar (batido, pollo con arroz, etc.) para recuperar.",
                "Cena"           => "Proteína + vegetales + una porción de carbohidrato.",
            ],
        ],
        "Mantenimiento" => [
            "ajuste"       => 0,
            "proteina_g_kg"=> 1.6,
            "grasa_pct"    => 0.30,
            "resumen"      => "Plan balanceado para sostener el peso y la energía actuales.",
            "comidas"      => [
                "Desayuno"       => "Proteína + carbohidrato + fruta.",
                "Almuerzo"       => "Plato balanceado: la mitad en vegetales, un cuarto en proteína y un cuarto en carbohidrato.",
                "Tarde"          => "Una fruta, yogur o puñado de frutos secos.",
                "Cena"           => "Similar al almuerzo, en una porción algo más liviana.",
            ],
        ],
    ];
    $plantilla = $plantillas[$objetivo] ?? $plantillas["Mantenimiento"];
    $calorias = (int) (round(($tdee + $plantilla["ajuste"]) / 50) * 50);
    $calorias = max($calorias, 1200);


    $proteina_g = round($peso * $plantilla["proteina_g_kg"]);
    $grasa_g    = round(($calorias * $plantilla["grasa_pct"]) / 9);
    $carbos_g   = max(0, round(($calorias - ($proteina_g * 4) - ($grasa_g * 9)) / 4));

    $descripcion = $plantilla["resumen"] . "\n\n";
    $descripcion .= "Macros aproximados del día: {$proteina_g} g de proteína, {$carbos_g} g de carbohidratos, {$grasa_g} g de grasa.\n\n";
    $descripcion .= "Distribución sugerida:\n";
    foreach ($plantilla["comidas"] as $tiempo => $detalle) {
        $descripcion .= "• $tiempo: $detalle\n";
    }
    $descripcion .= "\nToma al menos 2 litros de agua al día y evita bebidas azucaradas. Ajusta las porciones según el hambre y la energía que sientas — esto es una guía, no una receta exacta.";

    return [$calorias, $descripcion];
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generar"])) {
    $id_usuario_gen = $_POST["id_usuario"] ?? "";
    $objetivo_gen   = $_POST["objetivo"] ?? "Mantenimiento";
    if ($id_usuario_gen !== "" && in_array((int)$id_usuario_gen, $ids_permitidos, true)) {
        $stmt = mysqli_prepare($conexion, "SELECT sexo, edad, estatura, peso FROM perfil WHERE id_usuario=?");
        mysqli_stmt_bind_param($stmt, "i", $id_usuario_gen);
        mysqli_stmt_execute($stmt);
        $perfil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($perfil) {
            [$sugerido_calorias, $sugerido_descripcion] = generar_plan($perfil["sexo"], $perfil["edad"], $perfil["estatura"], $perfil["peso"], $objetivo_gen);
        }
    }
    $_POST["objetivo"] = $objetivo_gen; 
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["generar"])) {
    $id_plan       = $_POST["id_plan"] ?? "";
    $nombre_plan   = trim($_POST["nombre_plan"] ?? "");
    $objetivo      = $_POST["objetivo"] ?? "Mantenimiento";
    $calorias      = $_POST["calorias_diarias"] ?? "";
    $descripcion   = trim($_POST["descripcion"] ?? "");
    $id_usuario    = $_POST["id_usuario"] ?? "";
    $id_usuario_db = $id_usuario === "" ? null : (int)$id_usuario;
    $auto          = isset($_POST["fue_generado"]) ? 1 : 0;


    $propietario_valido = $es_admin || ($id_usuario_db !== null && in_array($id_usuario_db, $ids_permitidos, true));

    if ($nombre_plan === "" || $calorias === "") {
        $error = "El nombre del plan y las calorías diarias son obligatorios.";
    } elseif (!$propietario_valido) {
        $error = "Solo puedes crear o editar planes para tus estudiantes asignados.";
    } elseif ($id_plan === "") {
        $sql = "INSERT INTO plan_alimentacion (nombre_plan, objetivo, calorias_diarias, descripcion, generado_automaticamente, id_usuario) VALUES (?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssisii", $nombre_plan, $objetivo, $calorias, $descripcion, $auto, $id_usuario_db);
        mysqli_stmt_execute($stmt);
        $exito = "Plan de alimentación creado correctamente.";
    } else {
        if ($es_admin) {
            $sql = "UPDATE plan_alimentacion SET nombre_plan=?, objetivo=?, calorias_diarias=?, descripcion=?, id_usuario=? WHERE id_plan=?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ssisii", $nombre_plan, $objetivo, $calorias, $descripcion, $id_usuario_db, $id_plan);
        } else {
            $placeholders = implode(",", array_fill(0, count($ids_permitidos), "?"));
            $sql = "UPDATE plan_alimentacion SET nombre_plan=?, objetivo=?, calorias_diarias=?, descripcion=?, id_usuario=?
                    WHERE id_plan=? AND id_usuario IN ($placeholders)";
            $stmt = mysqli_prepare($conexion, $sql);
            $tipos = "ssisii" . str_repeat("i", count($ids_permitidos));
            $valores = array_merge([$nombre_plan, $objetivo, $calorias, $descripcion, $id_usuario_db, $id_plan], $ids_permitidos);
            mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
        }
        mysqli_stmt_execute($stmt);
        $exito = "Plan de alimentación actualizado correctamente.";
    }
}

if (isset($_GET["eliminar"])) {
    $id = (int)$_GET["eliminar"];
    if ($es_admin) {
        $stmt = mysqli_prepare($conexion, "DELETE FROM plan_alimentacion WHERE id_plan = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
    } else {
        $placeholders = implode(",", array_fill(0, count($ids_permitidos), "?"));
        $stmt = mysqli_prepare($conexion, "DELETE FROM plan_alimentacion WHERE id_plan = ? AND id_usuario IN ($placeholders)");
        $tipos = "i" . str_repeat("i", count($ids_permitidos));
        $valores = array_merge([$id], $ids_permitidos);
        mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
    }
    mysqli_stmt_execute($stmt);
    header("Location: planes_alimentacion.php?eliminado=1");
    exit;
}
if (isset($_GET["eliminado"])) { $exito = "Plan eliminado correctamente."; }

$plan_editar = null;
if (isset($_GET["editar"])) {
    $id = (int)$_GET["editar"];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM plan_alimentacion WHERE id_plan = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $plan_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($plan_editar && !$es_admin && !in_array((int)$plan_editar["id_usuario"], $ids_permitidos, true)) {
        $plan_editar = null;
        $error = "Ese plan no pertenece a ninguno de tus estudiantes asignados.";
    }
}

if ($es_admin) {
    $planes = mysqli_query($conexion, "
      SELECT p.*, u.nombre
      FROM plan_alimentacion p
      LEFT JOIN usuario u ON u.id_usuario = p.id_usuario
      ORDER BY p.fecha_creacion DESC");
} else {
    $placeholders = implode(",", array_fill(0, max(1, count($ids_permitidos)), "?"));
    $ids_para_in = $ids_permitidos ?: [0];
    $stmt = mysqli_prepare($conexion, "
      SELECT p.*, u.nombre
      FROM plan_alimentacion p
      JOIN usuario u ON u.id_usuario = p.id_usuario
      WHERE p.id_usuario IN ($placeholders)
      ORDER BY p.fecha_creacion DESC");
    mysqli_stmt_bind_param($stmt, str_repeat("i", count($ids_para_in)), ...$ids_para_in);
    mysqli_stmt_execute($stmt);
    $planes = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planes de Alimentación | RendiMAX</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include "partials/topbar.php"; ?>

<main class="panel-main">
  <div class="container">
    <div class="panel-encabezado"><div><h1>Planes de Alimentación</h1>
      <p><?= $es_admin ? "Genera un plan automático según el perfil del estudiante, o créalo manualmente." : "Crea planes para tus estudiantes asignados." ?></p></div></div>

    <?php if ($error): ?><div class="mensaje error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="mensaje exito"><?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if (!$es_admin && empty($ids_permitidos)): ?>
      <div class="mensaje error">Todavía no tienes grados asignados. Pídele a un administrador que te autorice desde Asignaciones.</div>
    <?php endif; ?>

    <div class="panel-card generador-card">
      <h2>🪄 Generador automático</h2>
      <p style="font-size:13px; margin-bottom:16px;">Elige un estudiante y un objetivo: calculamos sus calorías diarias (fórmula Mifflin-St Jeor) y redactamos una descripción según el objetivo. Luego puedes ajustarlo todo antes de guardar.</p>
      <form method="POST">
        <input type="hidden" name="generar" value="1">
        <div class="form-grid" style="margin-bottom:10px;">
          <div class="campo">
            <label>Estudiante</label>
            <select name="id_usuario" required>
              <option value="" disabled selected>Selecciona un estudiante</option>
              <?php mysqli_data_seek($estudiantes, 0); while ($e = mysqli_fetch_assoc($estudiantes)): ?>
                <option value="<?= $e['id_usuario'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="campo">
            <label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= (($_POST["objetivo"] ?? "") === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-secundario btn-sm">Generar sugerencia →</button>
      </form>
    </div>

    <div class="panel-card">
      <h2><?= $plan_editar ? "Editar plan" : "Nuevo plan de alimentación" ?></h2>
      <form method="POST">
        <input type="hidden" name="id_plan" value="<?= htmlspecialchars($plan_editar["id_plan"] ?? "") ?>">
        <?php if ($sugerido_calorias !== null): ?><input type="hidden" name="fue_generado" value="1"><?php endif; ?>
        <div class="form-grid">
          <div class="campo">
            <label>Nombre del plan</label>
            <input type="text" name="nombre_plan" placeholder="Ej: Plan definición - Julián" required
                   value="<?= htmlspecialchars($plan_editar["nombre_plan"] ?? "") ?>">
          </div>
          <div class="campo">
            <label>Objetivo</label>
            <select name="objetivo">
              <?php foreach (["Bajar de peso","Ganar músculo","Mantenimiento"] as $o): ?>
                <option value="<?= $o ?>" <?= (($plan_editar["objetivo"] ?? ($_POST["objetivo"] ?? "")) === $o) ? "selected" : "" ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label>Calorías diarias (kcal)</label>
            <input type="number" name="calorias_diarias" min="0" required
                   value="<?= htmlspecialchars($plan_editar["calorias_diarias"] ?? $sugerido_calorias ?? "") ?>">
          </div>
          <div class="campo">
            <label>Estudiante<?= $es_admin ? " (opcional)" : "" ?></label>
            <select name="id_usuario" <?= $es_admin ? "" : "required" ?>>
              <?php if ($es_admin): ?><option value="">General - disponible para todos</option><?php else: ?>
                <option value="" disabled <?= !$plan_editar ? "selected" : "" ?>>Selecciona un estudiante</option>
              <?php endif; ?>
              <?php mysqli_data_seek($estudiantes, 0); while ($e = mysqli_fetch_assoc($estudiantes)): ?>
                <option value="<?= $e['id_usuario'] ?>" <?= ((int)($plan_editar["id_usuario"] ?? ($_POST["id_usuario"] ?? 0)) === (int)$e['id_usuario']) ? "selected" : "" ?>>
                  <?= htmlspecialchars($e['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>
            <?php if (!$es_admin): ?><small class="ayuda-campo">Solo puedes elegir entre tus estudiantes asignados.</small><?php endif; ?>
          </div>
          <div class="campo full">
            <label>Descripción</label>
            <textarea name="descripcion" placeholder="Detalle del plan..."><?= htmlspecialchars($plan_editar["descripcion"] ?? $sugerido_descripcion) ?></textarea>
          </div>
        </div>
        <div class="form-acciones">
          <button type="submit" class="btn btn-verde btn-sm"><?= $plan_editar ? "Guardar cambios" : "Crear plan" ?></button>
          <?php if ($plan_editar): ?><a href="planes_alimentacion.php" class="btn btn-secundario btn-sm">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <h2><?= $es_admin ? "Planes creados" : "Planes de tus estudiantes" ?></h2>
      <div class="tabla-wrap">
        <table class="tabla-datos">
          <thead><tr><th>Nombre</th><th>Objetivo</th><th>Calorías</th><th>Asignado a</th><th>Origen</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($planes) === 0): ?>
              <tr><td colspan="6" class="vacio-tabla">Aún no hay planes de alimentación.</td></tr>
            <?php else: while ($p = mysqli_fetch_assoc($planes)): ?>
              <tr>
                <td><?= htmlspecialchars($p["nombre_plan"]) ?></td>
                <td><span class="pill"><?= htmlspecialchars($p["objetivo"]) ?></span></td>
                <td><?= (int)$p["calorias_diarias"] ?> kcal</td>
                <td><?= $p["nombre"] ? htmlspecialchars($p["nombre"]) : '<span class="pill gris">General</span>' ?></td>
                <td><?= $p["generado_automaticamente"] ? '<span class="pill gris">🪄 Auto</span>' : '<span class="pill gris">Manual</span>' ?></td>
                <td class="acciones-tabla">
                  <a class="editar" href="planes_alimentacion.php?editar=<?= $p['id_plan'] ?>">Editar</a>
                  <a class="eliminar" href="planes_alimentacion.php?eliminar=<?= $p['id_plan'] ?>" onclick="return confirm('¿Eliminar este plan?');">Eliminar</a>
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
