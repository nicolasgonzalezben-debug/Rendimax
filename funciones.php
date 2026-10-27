<?php

function verificarSesion() {
    if (!isset($_SESSION["id_usuario"])) {
        header("Location: iniciarsesion.php?error=sesion");
        exit;
    }
}


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

function nivelParaDias($dias) {
    $nivel = "Bronce";
    foreach (rangosRacha() as $r) {
        if ($dias >= $r["meta"]) { $nivel = $r["nombre"]; }
    }
    return $nivel;
}


function actualizarRacha($conexion, $idUsuario) {
    $hoy  = date("Y-m-d");
    $ayer = date("Y-m-d", strtotime("-1 day"));

    $stmt = mysqli_prepare($conexion, "SELECT * FROM racha WHERE id_usuario=? ORDER BY id_racha DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $racha = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$racha) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO racha (dias_consecutivos, fecha_inicio, fecha_ultima, nivel_personaje, id_usuario) VALUES (0, NULL, NULL, 'Bronce', ?)");
        mysqli_stmt_bind_param($stmt, "i", $idUsuario);
        mysqli_stmt_execute($stmt);
        $racha = ["dias_consecutivos" => 0, "fecha_inicio" => null, "fecha_ultima" => null];
    }

    $fechaUltima = $racha["fecha_ultima"];

    if ($fechaUltima === $hoy) {
        return; 
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
