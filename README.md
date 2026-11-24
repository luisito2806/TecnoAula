# TecnoAula

Plataforma escolar con un panel distinto para administrador, director, docente y alumno: cursos organizados en bloques y temas, tareas, calendario, calificaciones, foros, chat por curso y reportes.

Hecha con PHP (PDO) y MySQL, sin frameworks.

## Requisitos

- PHP 8.1 o más nuevo
- MySQL o MariaDB
- XAMPP sirve para las dos cosas

## Instalación

1. Copia la carpeta del proyecto a `C:\xampp\htdocs\TecnoAula` (o a la carpeta web de tu servidor).

2. Crea la base de datos y carga las tablas **en este orden**:

   ```bash
   mysql -u root -p -e "CREATE DATABASE TecnoAula CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p TecnoAula < database/00_esquema_inicial.sql
   mysql -u root -p TecnoAula < database/migracion_fase1_2.sql
   mysql -u root -p TecnoAula < database/migracion_fase3_4.sql
   ```

   En XAMPP el programa `mysql` está en `C:\xampp\mysql\bin\`. Si tu usuario `root` no tiene contraseña, quita el `-p`.

3. Copia `config.ejemplo.php` como `config.php` y pon tus datos de MySQL. `config.php` no se sube al repositorio.

4. Crea al primer Super Admin (el registro público solo crea alumnos):

   ```bash
   php database/crear_superadmin.php "Tu Nombre" tu@correo.com
   ```

   Te pedirá la contraseña (mínimo 8 caracteres).

5. Abre `http://localhost/TecnoAula/` e inicia sesión.

## Datos de ejemplo (opcional)

Para probar con cursos, alumnos, tareas, foros y chat ya llenos, ejecuta además, **después** de las migraciones:

```bash
mysql -u root -p TecnoAula < database/datos_ejemplo.sql
mysql -u root -p TecnoAula < database/datos_ejemplo_fase3_4.sql
```

Todos los usuarios de ejemplo tienen la contraseña `TecnoAula2026`; por ejemplo `ana.torres@tecnoaula.com` (docente y directora) o `diego.castillo@tecnoaula.com` (alumno). Úsalos solo en tu computadora.

## Estructura

| Carpeta | Qué hay |
|---|---|
| `admin/`, `director/`, `docente/`, `alumno/` | Páginas de cada rol |
| `api/` | Endpoints que usa JavaScript (usuarios, estructura del curso, chat) |
| `includes/` | Sesión, permisos, CSRF, diseño común e íconos |
| `assets/` | CSS y JavaScript |
| `database/` | Esquema inicial, migraciones, datos de ejemplo y `crear_superadmin.php` |
