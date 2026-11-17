# Documentación de Ciberseguridad — Book&Go Lite (Clínica Dental)

Este documento detalla las medidas de seguridad implementadas a nivel de aplicación, base de datos y gestión de sesiones para mitigar vulnerabilidades web comunes (OWASP Top 10).

---

## 1. Controles de Seguridad Implementados

### A. Prevención de Inyección SQL (SQLi)
* Uso exclusivo de **PDO (PHP Data Objects)** con consultas preparadas (`prepare` y `execute`) en todas las operaciones de lectura, inserción y actualización.
* Emulación de sentencias preparadas desactivada explícitamente (`PDO::ATTR_EMULATE_PREPARES => false`) en `config/conexion.php`.
* Sanitización y tipado estricto de parámetros GET y POST mediante `filter_input()` (`FILTER_VALIDATE_INT`, `FILTER_VALIDATE_FLOAT`, `FILTER_VALIDATE_EMAIL`).

### B. Protección contra Cross-Site Scripting (XSS)
* Todas las salidas dinámicas provenientes de la base de datos o entradas de usuario se escapan mediante la función centralizada `e()` usando `htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')`.
* Cabecera HTTP `X-XSS-Protection: 1; mode=block` habilitada en `config/conexion.php`.

### C. Protección contra Cross-Site Request Forgery (CSRF)
* Generación de un token criptográfico aleatorio por sesión mediante `bin2hex(random_bytes(32))`.
* Inclusión del campo oculto `csrf_token` en todos los formularios `POST` (`registro.php`, `login.php`, `servicio_detalle.php`, `mis_reservas.php`, `admin_panel.php`).
* Validación en servidor resistente a ataques de temporización mediante `hash_equals()`.

### D. Autenticación Segura y Mitigación de Fuerza Bruta
* Contraseñas almacenadas exclusivamente mediante hash seguro con algoritmo Bcrypt/Argon2 (`password_hash(..., PASSWORD_DEFAULT)`) y verificadas con `password_verify()`.
* **Bloqueo automático de cuenta:** El sistema contabiliza los intentos fallidos en la base de datos (`intentos_fallidos`). Al alcanzar **3 intentos fallidos**, la cuenta se bloquea automáticamente durante **5 minutos** (`bloqueado_hasta`).
* Regeneración de identificador de sesión tras iniciar sesión (`session_regenerate_id(true)`) para prevenir fijación de sesión (*Session Fixation*).

### E. Seguridad en Cookies y Cabeceras HTTP
* Cookies de sesión configuradas con `session.cookie_httponly = 1` y `session.use_only_cookies = 1` para impedir su lectura desde JavaScript.
* Cabeceras defensivas configuradas en `config/conexion.php`:
  * `X-Frame-Options: SAMEORIGIN` (mitigación de *Clickjacking*).
  * `X-Content-Type-Options: nosniff` (prevención de *MIME-sniffing*).

### F. Control de Acceso e Integridad Transaccional (IDOR y Race Conditions)
* **Separación de roles estricta:** Verificación en servidor de `$_SESSION['id_rol'] === 1` antes de cargar `admin_panel.php`.
* **Prevención de IDOR:** En `mis_reservas.php`, las consultas de cancelación y reseña filtran explícitamente por `id_cliente = $_SESSION['id_usuario']`, impidiendo que un paciente modifique citas de otro.
* **Transacciones SQL y bloqueo de filas:** El agendamiento y cancelación de citas emplean `beginTransaction()`, `SELECT ... FOR UPDATE`, `commit()` y `rollBack()`, garantizando que dos usuarios no puedan reservar el último cupo simultáneamente.

---

## 2. Matriz de Pruebas de Seguridad Verificadas

| Prueba Ejecutada | Mecanismo Evaluado | Resultado Esperado | Estado |
| :--- | :--- | :--- | :--- |
| Inserción de `' OR '1'='1` en login y buscador | Consultas preparadas PDO | Entrada tratada como texto literal sin alterar la consulta SQL | Verificado |
| Inserción de `<script>alert(1)</script>` en notas y reseñas | Escape HTML `htmlspecialchars` | El código se renderiza como texto plano sin ejecutarse | Verificado |
| Envío de formulario POST sin `csrf_token` o alterado | Validación `hash_equals` | Rechazo inmediato de la petición con mensaje de error | Verificado |
| 3 intentos fallidos consecutivos en `login.php` | Contador en BD + `bloqueado_hasta` | Bloqueo temporal de 5 minutos incluso con clave correcta | Verificado |
| Acceso directo por URL a `admin_panel.php` como `CLIENTE` | Control de acceso por rol | Redirección inmediata a `login.php` | Verificado |
| Doble reseña sobre una misma cita (`id_reserva`) | Restricción `UNIQUE` en BD + validación PHP | Bloqueo de duplicados en tabla `resenas` | Verificado |
