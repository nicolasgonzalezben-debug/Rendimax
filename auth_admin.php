<?php
/**
 * auth_admin.php
 * Incluir DESPUÉS de auth_check.php en toda página que solo debe
 * poder usar el Docente (Usuarios, Alimentación, Rutinas, Ejercicios).
 * Si el usuario en sesión es un estudiante, lo regresa al dashboard.
 */
if (($_SESSION["rol"] ?? "") !== "Docente") {
    header("Location: dashboard.php?acceso=denegado");
    exit;
}
