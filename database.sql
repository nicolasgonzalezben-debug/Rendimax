
CREATE DATABASE IF NOT EXISTS rendimax
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE rendimax;


CREATE TABLE IF NOT EXISTS usuario (
  id_usuario          INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(50)  NOT NULL,
  contrasena          VARCHAR(255) NOT NULL,      
  correo_electronico  VARCHAR(100) NOT NULL UNIQUE,
  fecha_registro      DATE NOT NULL DEFAULT (CURRENT_DATE),


  rol                 ENUM('estudiante','docente','administrador') NOT NULL DEFAULT 'estudiante'
 
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS asignacion_docente (
  id_asignacion    INT AUTO_INCREMENT PRIMARY KEY,
  id_docente       INT NOT NULL,
  grado            INT NOT NULL,              
  fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unico_docente_grado (id_docente, grado),
  FOREIGN KEY (id_docente) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS perfil (
  id_perfil     INT AUTO_INCREMENT PRIMARY KEY,
  sexo          VARCHAR(20),
  edad          INT,
  estatura      INT,       
  peso          INT,      
  objetivo      VARCHAR(100),   
  grado         INT,            
  id_usuario    INT NOT NULL UNIQUE,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS racha (
  id_racha            INT AUTO_INCREMENT PRIMARY KEY,
  dias_consecutivos   INT NOT NULL DEFAULT 0,
  fecha_inicio        DATE,
  fecha_ultima        DATE,
  nivel_personaje     VARCHAR(100),
  id_usuario          INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rutina (
  id_rutina           INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(50) NOT NULL,
  nivel               VARCHAR(100),
  tipo                VARCHAR(100),
  duracion_estimada   VARCHAR(100),
  id_usuario          INT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS usuario_rutina (
  id_usuario_rutina  INT AUTO_INCREMENT PRIMARY KEY,
  fecha_asignacion   DATE,
  estado             INT,          
  favorita           VARCHAR(3),   
  id_usuario         INT NOT NULL,
  id_rutina          INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (id_rutina)  REFERENCES rutina(id_rutina)   ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS progreso (
  id_progreso            INT AUTO_INCREMENT PRIMARY KEY,
  fecha                  DATE NOT NULL,
  peso                   INT,
  tiempo_entrenamiento   VARCHAR(100),
  rutinas_completadas    VARCHAR(100),
  id_usuario             INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS notificacion (
  id_notificacion  INT AUTO_INCREMENT PRIMARY KEY,
  mensaje          VARCHAR(200) NOT NULL,
  fecha            DATE NOT NULL,
  estado           VARCHAR(20),     -- p. ej. 'Leida' / 'No leida'
  id_usuario       INT NOT NULL,
  tipo             VARCHAR(20),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS ejercicios (
  id_ejercicio     INT AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(50) NOT NULL,
  tipo             ENUM('Cardio','Fuerza','Flexibilidad','Movilidad') NOT NULL DEFAULT 'Fuerza',
  instrucciones    VARCHAR(100),
  descripcion      VARCHAR(100),
  id_usuario       INT NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS rutina_ejercicio (
  id_rutina     INT NOT NULL,
  id_ejercicio  INT NOT NULL,
  series        TINYINT UNSIGNED DEFAULT 3,
  repeticiones  VARCHAR(20) DEFAULT '12',
  PRIMARY KEY (id_rutina, id_ejercicio),
  FOREIGN KEY (id_rutina)    REFERENCES rutina(id_rutina)       ON DELETE CASCADE,
  FOREIGN KEY (id_ejercicio) REFERENCES ejercicios(id_ejercicio) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS plan_alimentacion (
  id_plan                    INT AUTO_INCREMENT PRIMARY KEY,
  nombre_plan                VARCHAR(100) NOT NULL,
  objetivo                   ENUM('Bajar de peso','Ganar músculo','Mantenimiento') NOT NULL,
  calorias_diarias           INT NOT NULL,
  descripcion                TEXT,
  generado_automaticamente   TINYINT(1) NOT NULL DEFAULT 0,
  id_usuario                 INT NULL,  
  fecha_creacion             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;


ALTER TABLE rutina
  ADD COLUMN objetivo ENUM('Bajar de peso','Ganar músculo','Mantenimiento') NULL AFTER tipo,
  ADD COLUMN generado_automaticamente TINYINT(1) NOT NULL DEFAULT 0 AFTER duracion_estimada,
  ADD COLUMN descripcion TEXT NULL AFTER duracion_estimada;