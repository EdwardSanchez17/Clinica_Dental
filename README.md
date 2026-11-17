# Book&Go Lite — Clínica Dental Sonrisa Sana

Sistema web de gestión de citas odontológicas con catálogo de tratamientos, control transaccional de cupos en horarios, sistema de reseñas verificadas y panel administrativo protegido por roles.

## Equipo de Desarrollo
* **Edward Sanchez**
* **Edgardo Cordoba**
* **David Rios**
* **Jean Llanten**

---

## Tecnologías Utilizadas
* **Backend:** PHP 8+ con extensión PDO (consultas preparadas nativas).
* **Base de Datos:** MySQL / MariaDB (Motor InnoDB, 7 tablas relacionales).
* **Frontend:** HTML5 semántico, CSS3 responsivo (`css/estilos.css`).
* **Servidor Local:** Apache + MySQL (XAMPP).

---

## Estructura del Proyecto
```text
/BookGoLite (o /Parcial2)
├── /config
│   └── conexion.php         # Conexión PDO, sesiones seguras, cabeceras HTTP y CSRF
├── /css
│   └── estilos.css          # Hoja de estilos responsiva de la clínica dental
├── /database
│   └── bookgo_lite.sql      # Script SQL completo con estructura y datos de prueba
├── /security
│   └── SECURITY.md          # Documentación de controles y pruebas de seguridad
├── admin_panel.php          # Gestión de tratamientos, horarios y estados de citas (ADMIN)
├── index.php                # Catálogo público con búsqueda, filtros por especialidad y paginación
├── login.php                # Autenticación con bloqueo temporal por intentos fallidos
├── logout.php               # Cierre y destrucción segura de sesión
├── mis_reservas.php         # Panel del paciente: historial, cancelación y reseñas (CLIENTE)
├── registro.php             # Registro público de pacientes con rol CLIENTE por defecto
├── servicio_detalle.php     # Ficha del tratamiento, agendamiento transaccional y reseñas
└── README.md                # Documentación general del proyecto
```

---

## Guía de Instalación y Despliegue Local

1. **Copiar archivos al servidor:**
   Colocar la carpeta del proyecto dentro del directorio público del servidor local (por ejemplo, `C:\xampp\htdocs\Parcial2\`).

2. **Iniciar servicios:**
   Abrir el panel de control de **XAMPP** e iniciar **Apache** y **MySQL**.

3. **Importar la base de datos:**
   * Abrir `http://localhost/phpmyadmin/` en el navegador.
   * Crear una base de datos llamada `bookgo_lite` con cotejamiento `utf8mb4_unicode_ci`.
   * Ir a la pestaña **Importar**, seleccionar el archivo `database/bookgo_lite.sql` y ejecutar.

4. **Configurar credenciales (si aplica):**
   Verificar en `config/conexion.php` que el usuario (`root`) y contraseña (`''`) coincidan con el entorno local.

5. **Acceso y roles:**
   * Abrir `http://localhost/Parcial2/index.php`.
   * Todo usuario registrado desde `registro.php` recibe automáticamente el rol **CLIENTE (`id_rol = 2`)**.
   * Para asignar privilegios de **ADMIN (`id_rol = 1`)**, actualizar el campo `id_rol` a `1` en la tabla `usuarios` desde phpMyAdmin.
