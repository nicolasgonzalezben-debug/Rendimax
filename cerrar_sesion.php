<?php
require_once "config.php";

/**
 * cerrar_sesion.php
 * Destruye la sesión por completo: los datos en el servidor Y la
 * cookie en el navegador. Esto es importante — session_destroy()
 * solo no borra la cookie, así que sin este paso el navegador
 * podría seguir mandando el mismo PHPSESSID de antes.
 */
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();
    setcookie(
        session_name(), "", time() - 42000,
        $parametros["path"], $parametros["domain"],
        $parametros["secure"], $parametros["httponly"]
    );
}

session_destroy();

header("Location: iniciarsesion.php");
exit;
