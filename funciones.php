<?php
/**
 * funciones.php
 * -----------------------------------------------------------------
 * Aquí vive TODO el control de acceso del sistema. Ninguna página
 * debe decidir por su cuenta si un usuario puede entrar o no:
 * siempre llama a estas funciones.
 *
 * Regla de oro: nunca confiamos en $_POST, $_GET, cookies ni nada
 * que venga del navegador para saber el rol de alguien. El rol
 * SOLO sale de $_SESSION["rol"], que el propio servidor escribió
 * en procesar_login.php justo después de validar la contraseña
 * contra la base de datos.
 * -----------------------------------------------------------------
 */

/**
 * verificarSesion()
 * Comprueba que haya alguien logueado. Si no, lo manda al login.
 * Úsala al principio de CUALQUIER página que requiera estar logueado.
 */
function verificarSesion() {
    if (!isset($_SESSION["id_usuario"])) {
        header("Location: iniciarsesion.php?error=sesion");
        exit;
    }
}

/**
 * exigirRol($rolesPermitidos)
 * Comprueba sesión Y que el rol del usuario esté en la lista permitida.
 * Si no cumple, lo regresa al dashboard con un aviso.
 *
 * Ejemplos:
 *   exigirRol("administrador");
 *   exigirRol(["docente", "administrador"]);
 */
function exigirRol($rolesPermitidos) {
    verificarSesion();

    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    $miRol = $_SESSION["rol"] ?? "";

    if (!in_array($miRol, $rolesPermitidos, true)) {
        header("Location: dashboard.php?acceso=denegado");
        exit;
    }
}

/** Atajos para preguntar el rol actual sin repetir código */
function esAdministrador() { return ($_SESSION["rol"] ?? "") === "administrador"; }
function esDocente()       { return ($_SESSION["rol"] ?? "") === "docente"; }
function esEstudiante()    { return ($_SESSION["rol"] ?? "") === "estudiante"; }

/**
 * esDocenteDeEstudiante()
 * Un docente NO puede ver a cualquier estudiante: solo a los de los
 * GRADOS que un administrador le autorizó en asignacion_docente
 * (por ejemplo, un docente de "octavo a décimo" tiene autorizados
 * los grados 8, 9 y 10). Un estudiante es "suyo" si el grado guardado
 * en perfil.grado coincide con alguno de esos grados autorizados.
 * Esta función responde true/false consultando la base de datos
 * (con consulta preparada).
 */
function esDocenteDeEstudiante($conexion, $idDocente, $idEstudiante) {
    $stmt = mysqli_prepare($conexion,
        "SELECT 1 FROM asignacion_docente ad
         JOIN perfil p ON p.grado = ad.grado
         WHERE ad.id_docente = ? AND p.id_usuario = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $idDocente, $idEstudiante);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    return mysqli_stmt_num_rows($stmt) > 0;
}

/**
 * rangosRacha()
 * Única fuente de verdad para los rangos de la mascota (nombre, días
 * necesarios e imagen). Antes esta lista estaba copiada y pegada en
 * cada página que la necesitaba; ahora vive en un solo lugar.
 */
function rangosRacha() {
    return [
        ["nombre" => "Bronce",   "meta" => 1,   "img" => "bronce"],
        ["nombre" => "Plata",    "meta" => 3,   "img" => "plata"],
        ["nombre" => "Oro",      "meta" => 7,   "img" => "oro"],
        ["nombre" => "Diamante", "meta" => 14,  "img" => "diamante"],
        ["nombre" => "Titanio",  "meta" => 50,  "img" => "titanio"],
        ["nombre" => "Elite",    "meta" => 100, "img" => "elite"],
        ["nombre" => "Invictus", "meta" => 365, "img" => "invictus"],
    ];
}

/** Dado un número de días, devuelve el nombre del rango que le corresponde. */
function nivelParaDias($dias) {
    $nivel = "Bronce";
    foreach (rangosRacha() as $r) {
        if ($dias >= $r["meta"]) { $nivel = $r["nombre"]; }
    }
    return $nivel;
}

/**
 * actualizarRacha()
 * El corazón del sistema de rachas: lo llama mi_progreso.php cada vez
 * que un estudiante registra su progreso del DÍA DE HOY (no cuenta si
 * registra una fecha pasada — eso evitaría poder "inflar" la racha).
 *
 * Reglas, basadas en la fecha de la ÚLTIMA vez que se registró algo
 * (fecha_ultima):
 *   - Si ya había registrado algo HOY: no cambia nada (para no sumar
 *     dos veces el mismo día si edita o crea varios registros).
 *   - Si la última vez fue AYER: la racha sigue, +1 día.
 *   - Si la última vez fue antes de ayer (o nunca): la racha se perdió
 *     y vuelve a empezar en 1.
 * Al final recalcula el rango (nivel_personaje) según los días nuevos.
 */
function actualizarRacha($conexion, $idUsuario) {
    $hoy  = date("Y-m-d");
    $ayer = date("Y-m-d", strtotime("-1 day"));

    $stmt = mysqli_prepare($conexion, "SELECT * FROM racha WHERE id_usuario=? ORDER BY id_racha DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $racha = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$racha) {
        // No debería pasar (se crea en el registro), pero por seguridad
        // creamos la fila si faltara.
        $stmt = mysqli_prepare($conexion, "INSERT INTO racha (dias_consecutivos, fecha_inicio, fecha_ultima, nivel_personaje, id_usuario) VALUES (0, NULL, NULL, 'Bronce', ?)");
        mysqli_stmt_bind_param($stmt, "i", $idUsuario);
        mysqli_stmt_execute($stmt);
        $racha = ["dias_consecutivos" => 0, "fecha_inicio" => null, "fecha_ultima" => null];
    }

    $fechaUltima = $racha["fecha_ultima"];

    if ($fechaUltima === $hoy) {
        return; // ya contaba hoy, no se toca nada
    }

    if ($fechaUltima === $ayer) {
        $dias = (int)$racha["dias_consecutivos"] + 1;
        $fechaInicio = $racha["fecha_inicio"] ?: $hoy;
    } else {
        $dias = 1;
        $fechaInicio = $hoy;
    }
    $nivel = nivelParaDias($dias);

    $stmt = mysqli_prepare($conexion, "UPDATE racha SET dias_consecutivos=?, fecha_inicio=?, fecha_ultima=?, nivel_personaje=? WHERE id_usuario=?");
    mysqli_stmt_bind_param($stmt, "isssi", $dias, $fechaInicio, $hoy, $nivel, $idUsuario);
    mysqli_stmt_execute($stmt);
}

/**
 * estudiantesDeDocente()
 * Devuelve (como mysqli_result) todos los estudiantes que están en
 * algún grado autorizado para este docente. Se usa para armar los
 * <select> y para calcular $ids_permitidos en los módulos de
 * Alimentación, Rutinas y Monitoreo.
 */
function estudiantesDeDocente($conexion, $idDocente) {
    $stmt = mysqli_prepare($conexion, "
        SELECT DISTINCT u.id_usuario, u.nombre, p.grado
        FROM usuario u
        JOIN perfil p ON p.id_usuario = u.id_usuario
        JOIN asignacion_docente ad ON ad.grado = p.grado
        WHERE ad.id_docente = ? AND u.rol = 'estudiante'
        ORDER BY u.nombre");
    mysqli_stmt_bind_param($stmt, "i", $idDocente);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}
