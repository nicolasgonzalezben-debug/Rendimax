# RendiMAX — Sistema de Roles y Permisos

Explicación simple, pensada para un proyecto de grado 11, de cómo
funciona la seguridad de RendiMAX con sus 3 roles: **estudiante**,
**docente** y **administrador**.

## 1. La tabla de usuarios (equivalente a "tabla estudiantes")

En RendiMAX, TODAS las cuentas (estudiantes, docentes y
administradores) viven en una sola tabla llamada `usuario`, y el
campo `rol` dice qué es cada quien. Los datos que solo tienen
sentido para un estudiante (edad, estatura, peso, objetivo) están
en una tabla aparte, `perfil`, conectada 1 a 1.

```sql
CREATE TABLE usuario (
  id_usuario          INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(50)  NOT NULL,
  contrasena          VARCHAR(255) NOT NULL,   -- siempre un hash, nunca texto plano
  correo_electronico  VARCHAR(100) NOT NULL UNIQUE,
  fecha_registro      DATE NOT NULL DEFAULT (CURRENT_DATE),
  rol                 ENUM('estudiante','docente','administrador')
                       NOT NULL DEFAULT 'estudiante'
);
```

`rol` es un `ENUM`: solo puede valer una de esas 3 palabras exactas.
Eso ya es una primera protección — ni por error se puede guardar
"Administrador" con mayúscula o "admin" abreviado.

Además está `asignacion_docente`, la tabla que dice qué **grados**
puede ver cada docente (no estudiante por estudiante, sino por
grupo — por ejemplo, un docente de octavo a décimo tiene 3 filas, y
otro docente de los onces tiene 1 sola fila):

```sql
CREATE TABLE asignacion_docente (
  id_asignacion    INT AUTO_INCREMENT PRIMARY KEY,
  id_docente       INT NOT NULL,
  grado            INT NOT NULL,   -- 6 a 11 (sexto a once)
  fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unico_docente_grado (id_docente, grado),
  FOREIGN KEY (id_docente) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);
```

Cada estudiante guarda su propio grado en `perfil.grado`. Un
estudiante queda automáticamente a cargo de cualquier docente que
tenga asignado ese mismo grado — no hace falta asignarlo uno por
uno. Esto se administra desde `admin_asignaciones.php`.

El SQL completo (con todas las demás tablas del proyecto) está en
`database.sql`.

## 2. La idea central del sistema de roles

**El navegador nunca decide nada.** Todo lo que el usuario ve en
pantalla (botones, menús) es solo decoración — la seguridad de
verdad pasa en PHP, en el servidor, consultando la base de datos.

El flujo es siempre el mismo:

1. El usuario inicia sesión → PHP verifica su contraseña con
   `password_verify()` contra la base de datos.
2. Si es correcta, PHP lee el `rol` que está guardado en la base de
   datos (no el que venga de ningún formulario) y lo guarda en
   `$_SESSION["rol"]`.
3. En cada página, antes de mostrar nada, PHP vuelve a preguntar
   `$_SESSION["rol"]` y decide si deja pasar o no.

Nadie puede "mentir" sobre su rol porque nunca se le pregunta al
navegador — se lee del lado del servidor, de una sesión que el
propio servidor creó.

## 3. Archivo de funciones: `funciones.php`

Es el corazón del sistema. Todas las páginas lo usan (se carga
automáticamente porque `config.php` lo incluye). Trae 3 funciones
principales, tal como las pediste:

```php
verificarSesion();              // ¿Hay alguien logueado? Si no, al login.
exigirRol("administrador");     // ¿Es administrador? Si no, fuera.
exigirRol(["docente","administrador"]); // ¿Es docente O administrador?
```

También trae `esAdministrador()`, `esDocente()`, `esEstudiante()`
(para preguntar el rol sin cortar la página), y
`esDocenteDeEstudiante($conexion, $idDocente, $idEstudiante)`, que
comprueba en la tabla `asignacion_docente` si un docente tiene
permiso de ver a un estudiante en particular.

## 4. Cómo se protege cada página (ejemplo real)

Así empieza `usuarios.php` (el panel de administración):

```php
require_once "config.php";
exigirRol("administrador");
```

Si un estudiante escribe `usuarios.php` directo en la URL, PHP
ejecuta `exigirRol("administrador")`, ve que su rol no está en la
lista permitida, y lo manda de vuelta al dashboard — **nunca llega a
ver el HTML de esa página**, aunque conozca la dirección exacta.

Así quedó protegida cada página:

| Página | Roles permitidos |
|---|---|
| `dashboard.php` | cualquiera con sesión iniciada |
| `usuarios.php`, `admin_asignaciones.php` | `administrador` |
| `planes_alimentacion.php`, `rutinas_entrenamiento.php`, `ejercicios.php` | `administrador` |
| `monitoreo_progreso.php` | `docente`, `administrador` (el docente solo ve a los suyos) |
| `mi_perfil.php`, `mi_plan.php`, `mi_rutina.php`, `mi_progreso.php`, `mi_racha.php` | `estudiante` |

## 5. Registro público: siempre "estudiante"

`registrarestudiante.php` **no tiene ningún campo para elegir rol**
— ni oculto, ni de texto, nada. Y en `procesar_registro.php`, el
servidor fija el valor a mano:

```php
// El rol NUNCA sale de $_POST. Se escribe aquí, fijo, siempre así:
$rol = "estudiante";
```

Así que aunque alguien use las herramientas del navegador para
inventarse un campo `rol=administrador` y mandarlo por POST, esa
línea de PHP lo ignora por completo: ese valor jamás se usa.

## 6. Inicio de sesión y sesiones PHP

`procesar_login.php` valida usuario/contraseña con
`password_verify()` y, si son correctos, guarda en la sesión los
datos que las demás páginas van a necesitar:

```php
$_SESSION["id_usuario"] = $usuario["id_usuario"];
$_SESSION["nombre"]     = $usuario["nombre"];
$_SESSION["rol"]        = $usuario["rol"];   // tal cual está en la BD
```

De ahí en adelante, **toda** página que necesite saber quién es el
usuario o qué puede hacer, lee `$_SESSION`, nunca vuelve a preguntarle
nada al navegador.

## 7. Panel de administración: asignar roles

Desde `usuarios.php`, un administrador puede cambiar la cuenta de un
usuario entre **estudiante** y **docente** — nada más. Dos capas de
protección para que nunca se pueda asignar "administrador" desde ahí:

```php
// 1) El <select> del HTML solo ofrece estas dos opciones:
<option value="estudiante">Estudiante</option>
<option value="docente">Docente</option>

// 2) Aunque alguien mande otro valor por POST a mano, el servidor
//    solo acepta estos dos y usa "estudiante" si viene cualquier otra cosa:
$ROLES_ASIGNABLES = ["estudiante", "docente"];
$rol = in_array($_POST["rol"] ?? "", $ROLES_ASIGNABLES, true) ? $_POST["rol"] : "estudiante";

// 3) Y por si acaso, hasta la consulta SQL de UPDATE está limitada
//    a filas que ya son estudiante o docente — nunca toca administradores:
UPDATE usuario SET ... WHERE id_usuario=? AND rol IN ('estudiante','docente')
```

Las cuentas de administrador **ni siquiera aparecen** en la lista de
`usuarios.php` — ese panel es solo para gestionar estudiantes y
docentes.

`admin_asignaciones.php` es el panel donde el administrador decide
qué estudiantes puede ver cada docente (llena la tabla
`asignacion_docente`).

## 8. Cómo se crean los 4 administradores iniciales (de forma segura)

**Nunca por el registro público.** Se usa `instalar_admins.php`, un
script especial con dos candados:

1. Una **clave de instalación** secreta (una constante en el
   archivo) que solo ustedes 4 conocen. Sin ella, el formulario no
   procesa nada.
2. Si en la base de datos **ya existe** algún usuario con
   `rol='administrador'`, el script se niega a crear más — así, si
   alguien encuentra el archivo después, no sirve de nada.

Pasos para usarlo:

1. Abran `instalar_admins.php` y cambien el valor de
   `CLAVE_INSTALACION` por una propia (arriba del archivo).
2. Entren a `instalar_admins.php` en el navegador, escriban la clave
   y los datos (nombre, correo, contraseña) de los 4 administradores.
3. Al terminar, **borren el archivo `instalar_admins.php` del
   servidor** (o como mínimo cambien la clave) — ya cumplió su
   función.

## 9. Qué puede hacer cada rol (ejemplos)

| | Estudiante | Docente | Administrador |
|---|---|---|---|
| Ver su propio plan de alimentación y rutina | ✅ | — | — |
| Registrar su propio progreso (peso, entrenamientos) | ✅ | — | — |
| Ver su mascota / racha / rango | ✅ | — | — |
| Editar su propio perfil y contraseña | ✅ | — | — |
| Ver el progreso de estudiantes **autorizados** | ❌ | ✅ | ✅ (de todos) |
| Crear/editar/eliminar planes, rutinas, ejercicios | ❌ | ❌ | ✅ |
| Crear o eliminar cuentas de usuario | ❌ | ❌ | ✅ (solo estudiante/docente) |
| Cambiar el rol de otro usuario | ❌ | ❌ | ✅ (solo entre estudiante ↔ docente) |
| Asignar qué estudiantes ve cada docente | ❌ | ❌ | ✅ |
| Convertirse en administrador desde la interfaz | ❌ | ❌ | ❌ (nadie puede, ni siquiera otro admin) |

## 10. Instalación

1. Copia la carpeta `RendiMAX` a tu `htdocs` de XAMPP.
2. Importa `database.sql` completo en phpMyAdmin.
3. Ajusta `config.php` si tu usuario/clave de MySQL son distintos.
4. Entra a `instalar_admins.php` y crea a los 4 administradores
   (luego bórralo o cambia su clave).
5. Desde una cuenta administradora: crea docentes/estudiantes en
   **Usuarios**, asigna estudiantes a cada docente en
   **Asignaciones**, y carga ejercicios/planes/rutinas.
6. Los estudiantes se registran ellos mismos en
   `registrarestudiante.php` — siempre entran como estudiante.

## 11. Condicionales del registro (sin cambios)

Nombre con espacio (nombre y apellido), correo válido y único,
contraseña con mínimo 8 caracteres y un carácter especial, y
confirmación de contraseña coincidente — validado en
`procesar_registro.php` y en el formulario.

## 12. Pendiente / siguientes pasos sugeridos

- Ahora mismo, si el administrador elimina o desactiva una cuenta,
  no hay pantalla de "recuperar contraseña" — si quieres, se puede
  agregar.
- Si más adelante quieres que un docente pueda ver el plan/rutina de
  sus estudiantes asignados (no solo el progreso), dime y lo agrego
  a `monitoreo_progreso.php`.
- La tabla `notificacion` del diccionario original sigue sin una
  pantalla propia.
