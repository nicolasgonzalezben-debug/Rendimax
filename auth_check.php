<?php
/**
 * auth_check.php
 * Incluir al inicio de cada página del dashboard (después de config.php).
 * Si no hay sesión activa, regresa al login.
 */
if (!isset($_SESSION["id_usuario"])) {
    header("Location: iniciarsesion.php?error=sesion");
    exit;
}
