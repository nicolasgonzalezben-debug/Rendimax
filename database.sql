-- =========================================================
--  Base de datos: RendiMAX
--  Institución Educativa Federico Ozanam
--  Construida a partir de "Diccionario_Rendimax.xlsx"
-- =========================================================

CREATE DATABASE IF NOT EXISTS rendimax
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE rendimax;

-- ---------------------------------------------------------
-- COD 01: usuario
-- Cuenta de acceso al sistema (docente o estudiante)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario (
  id_usuario          INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(50)  NOT NULL,
  contrasena          VARCHAR(255) NOT NULL,      -- password_hash() de PHP, nunca texto plano
  correo_electronico  VARCHAR(100) NOT NULL UNIQUE,
  fecha_registro      DATE NOT NULL DEFAULT (CURRENT_DATE),

  -- Rol de acceso. Los 3 roles del sistema: estudiante, docente, administrador.
  -- IMPORTANTE: el registro público SIEMPRE guarda 'estudiante' (ver procesar_registro.php).
  -- Los administradores se crean únicamente con instalar_admins.php (ver README).
  rol                 ENUM('estudiante','docente','administrador') NOT NULL DEFAULT 'estudiante'
  -- Si tu base de datos ya existía ANTES de este cambio y el ENUM
  -- quedó guardado con mayúsculas (por ejemplo
  -- enum('Administrador','Docente','Estudiante')), todo el código PHP
  -- compara en minúscula y NUNCA va a coincidir — ese es un bug común.
  --
  -- Para corregirlo SIN mezclar el rol de nadie, hay que mantener el
  -- mismo orden interno y solo cambiar cómo se escribe cada palabra.
  -- Primero revisa el orden real con:
  --   SHOW COLUMNS FROM usuario LIKE 'rol';
  -- Si te muestra enum('Administrador','Docente','Estudiante') (en ese
  -- orden), corre exactamente esto (respetando el mismo orden):
  --   ALTER TABLE usuario
  --     MODIFY COLUMN rol ENUM('administrador','docente','estudiante') NOT NULL DEFAULT 'estudiante';
  -- Si el orden que te muestra es distinto, escribe las 3 palabras en
  -- minúscula pero EN ESE MISMO ORDEN, o los roles de los usuarios que
  -- ya existen se van a mezclar entre sí.
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- asignacion_docente (NUEVA, por GRUPO/GRADO)
-- A qué grados tiene autorizado supervisar cada docente —
-- por ejemplo, un docente de "octavo a décimo" tiene 3 filas
-- (grado 8, 9 y 10) y otro docente de "los onces" tiene 1 fila
-- (grado 11). Un estudiante queda automáticamente a cargo de
-- CUALQUIER docente que tenga asignado su mismo grado (ver
-- columna perfil.grado más abajo) — no hace falta asignar
-- estudiante por estudiante. Un administrador es quien crea
-- estas asignaciones (ver admin_asignaciones.php).
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS asignacion_docente (
  id_asignacion    INT AUTO_INCREMENT PRIMARY KEY,
  id_docente       INT NOT NULL,
  grado            INT NOT NULL,              -- 6 a 11 (sexto a once)
  fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unico_docente_grado (id_docente, grado),
  FOREIGN KEY (id_docente) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Si tu base de datos ya existía con la versión anterior de esta
-- tabla (una fila por cada pareja docente-estudiante), migra así,
-- SIN perder lo que ya tenías, corriendo esto en phpMyAdmin:
--   ALTER TABLE asignacion_docente ADD COLUMN grado INT NULL AFTER id_docente;
--   UPDATE asignacion_docente ad
--     JOIN perfil p ON p.id_usuario = ad.id_estudiante
--     SET ad.grado = p.grado;
--   ALTER TABLE asignacion_docente DROP FOREIGN KEY <nombre_fk_id_estudiante>;
--   ALTER TABLE asignacion_docente DROP COLUMN id_estudiante;
--   ALTER TABLE asignacion_docente MODIFY COLUMN grado INT NOT NULL;
--   ALTER TABLE asignacion_docente DROP INDEX unico_docente_estudiante;
--   ALTER TABLE asignacion_docente ADD UNIQUE KEY unico_docente_grado (id_docente, grado);
-- (Esto solo funciona si ya le pusiste grado a tus estudiantes en
-- perfil.grado primero — ver la nota de migración de la tabla perfil.)

-- ---------------------------------------------------------
-- COD 06: perfil
-- Datos físicos/personales de cada usuario (1 a 1 con usuario)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS perfil (
  id_perfil     INT AUTO_INCREMENT PRIMARY KEY,
  sexo          VARCHAR(20),
  edad          INT,
  estatura      INT,        -- cm
  peso          INT,        -- kg
  objetivo      VARCHAR(100),   -- SOLO aplica a estudiantes; en docentes queda NULL (ver usuarios.php)
  grado         INT,            -- SOLO aplica a estudiantes: 6 a 11 (sexto a once). En docentes queda NULL.
  id_usuario    INT NOT NULL UNIQUE,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Si tu base de datos ya existía sin la columna grado, agrégala así:
--   ALTER TABLE perfil ADD COLUMN grado INT NULL AFTER objetivo;
-- y luego ponle un grado a cada estudiante ya registrado (editándolo
-- desde Usuarios, o con UPDATE perfil SET grado=11 WHERE id_usuario=...).

-- ---------------------------------------------------------
-- COD 02: racha
-- Seguimiento de días consecutivos cumpliendo hábitos
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS racha (
  id_racha            INT AUTO_INCREMENT PRIMARY KEY,
  dias_consecutivos   INT NOT NULL DEFAULT 0,
  fecha_inicio        DATE,
  fecha_ultima        DATE,
  nivel_personaje     VARCHAR(100),
  id_usuario          INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
-- La racha ahora SÍ avanza sola: cada vez que un estudiante registra su
-- progreso del día de hoy en Mi Progreso, funciones.php->actualizarRacha()
-- suma un día (o la reinicia si dejó pasar más de un día). Antes nada
-- incrementaba dias_consecutivos y quedaba siempre en 0.
-- Si tu base de datos ya tenía estudiantes registrados con fecha_ultima
-- = su fecha de registro (el comportamiento viejo), puedes limpiarla así
-- para que todos arranquen en 0 de verdad:
--   UPDATE racha SET dias_consecutivos=0, fecha_inicio=NULL, fecha_ultima=NULL;

-- ---------------------------------------------------------
-- COD 04: rutina
-- Características de cada rutina de entrenamiento.
-- id_usuario NULL = rutina general disponible para todos;
-- con valor = rutina asignada a ese estudiante en particular.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS rutina (
  id_rutina           INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(50) NOT NULL,
  nivel               VARCHAR(100),
  tipo                VARCHAR(100),
  duracion_estimada   VARCHAR(100),
  id_usuario          INT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COD 03: usuario_rutina
-- Seguimiento de qué rutina tiene asignada cada usuario.
-- (Se agregó id_rutina: el diccionario original no lo traía,
-- pero sin esa FK no se podría saber qué rutina es.)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario_rutina (
  id_usuario_rutina  INT AUTO_INCREMENT PRIMARY KEY,
  fecha_asignacion   DATE,
  estado             INT,          -- p. ej. 1 = en curso, 0 = finalizada
  favorita           VARCHAR(3),   -- 'Si' / 'No'
  id_usuario         INT NOT NULL,
  id_rutina          INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (id_rutina)  REFERENCES rutina(id_rutina)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COD 05: progreso
-- Evolución física y de entrenamiento de cada usuario
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS progreso (
  id_progreso            INT AUTO_INCREMENT PRIMARY KEY,
  fecha                  DATE NOT NULL,
  peso                   INT,
  tiempo_entrenamiento   VARCHAR(100),
  rutinas_completadas    VARCHAR(100),
  id_usuario             INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COD 07: notificacion
-- Mensajes que le llegan a cada usuario
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificacion (
  id_notificacion  INT AUTO_INCREMENT PRIMARY KEY,
  mensaje          VARCHAR(200) NOT NULL,
  fecha            DATE NOT NULL,
  estado           VARCHAR(20),     -- p. ej. 'Leida' / 'No leida'
  id_usuario       INT NOT NULL,
  tipo             VARCHAR(20),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COD 08: ejercicios
-- Ejercicios individuales y sus características.
-- id_usuario = docente que lo creó.
-- (Se agregó "tipo" para poder auto-generar rutinas filtrando
-- por tipo de ejercicio: Cardio, Fuerza, Flexibilidad, Movilidad)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS ejercicios (
  id_ejercicio     INT AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(50) NOT NULL,
  tipo             ENUM('Cardio','Fuerza','Flexibilidad','Movilidad') NOT NULL DEFAULT 'Fuerza',
  instrucciones    VARCHAR(100),
  descripcion      VARCHAR(100),
  id_usuario       INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- rutina_ejercicio (NUEVA)
-- Relación N a N: qué ejercicios componen cada rutina.
-- La usa el generador automático de rutinas.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS rutina_ejercicio (
  id_rutina     INT NOT NULL,
  id_ejercicio  INT NOT NULL,
  series        TINYINT UNSIGNED DEFAULT 3,
  repeticiones  VARCHAR(20) DEFAULT '12',
  PRIMARY KEY (id_rutina, id_ejercicio),
  FOREIGN KEY (id_rutina)    REFERENCES rutina(id_rutina)       ON DELETE CASCADE,
  FOREIGN KEY (id_ejercicio) REFERENCES ejercicios(id_ejercicio) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- plan_alimentacion (NUEVA)
-- El diccionario original no traía una tabla de alimentación;
-- se agrega para el CRUD de "Planes de Alimentación" y su
-- generador automático de calorías según el perfil del estudiante.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_alimentacion (
  id_plan                    INT AUTO_INCREMENT PRIMARY KEY,
  nombre_plan                VARCHAR(100) NOT NULL,
  objetivo                   ENUM('Bajar de peso','Ganar músculo','Mantenimiento') NOT NULL,
  calorias_diarias           INT NOT NULL,
  descripcion                TEXT,
  generado_automaticamente   TINYINT(1) NOT NULL DEFAULT 0,
  id_usuario                 INT NULL,   -- NULL = plan general, valor = asignado a ese estudiante
  fecha_creacion             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- rutina: se le agrega "objetivo" y "duracion_min" numérica para que el
-- generador automático pueda calcular y filtrar correctamente
-- (duracion_estimada de texto se conserva para mostrarla ya formateada).
-- También se agrega "descripcion": son las indicaciones detalladas que
-- lee el estudiante en Mi Rutina (antes el formulario la pedía pero
-- nunca se guardaba en la base de datos — quedaba siempre vacía).
ALTER TABLE rutina
  ADD COLUMN objetivo ENUM('Bajar de peso','Ganar músculo','Mantenimiento') NULL AFTER tipo,
  ADD COLUMN generado_automaticamente TINYINT(1) NOT NULL DEFAULT 0 AFTER duracion_estimada,
  ADD COLUMN descripcion TEXT NULL AFTER duracion_estimada;


-- ---------------------------------------------------------
-- Nota:
-- Las contraseñas NUNCA se guardan en texto plano.
-- Se guardan con password_hash() de PHP y se validan con
-- password_verify().
-- ---------------------------------------------------------
