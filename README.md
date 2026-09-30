# DelegaVoto - Sistema de Votación de Delegados

Un sistema web seguro, en tiempo real y *mobile-first* para elegir al delegado de clase usando cuentas institucionales de Google y votación presencial mediante código QR.

## Características Principales

*   **1 Alumno = 1 Voto (Google Auth):** Obliga a iniciar sesión con una cuenta de Google, garantizando que nadie pueda inventarse nombres o votar de forma anónima.
*   **Sistema Anti-Pillín (Prevención de Doble Voto):** Registra una cookie criptográfica tras emitir un voto. Si un usuario intenta usar otra cuenta en el mismo dispositivo, es bloqueado.
*   **Voto 100% Presencial:** El profesor proyecta un código QR dinámico con un token secreto. Evita que alumnos que no están en clase puedan votar.
*   **Actualizaciones en Vivo:** La lista de candidatos y el escrutinio de votos (con barras de progreso) se actualizan en tiempo real sin recargar la página.
*   **Automatización de Resultados:** Al cerrar la votación, envía correos automáticos por SMTP a toda la clase con el resultado.
*   **Diseño Antigravity / Glassmorphism:** Interfaz minimalista con fondo interactivo de partículas en 3D en Canvas.

---

## Instalación y Configuración

Para probar o alojar este proyecto (en local o en un VPS), debes configurar los siguientes parámetros en el archivo `config.php`:

### 1. Configuración de Google Auth (Login)
1. Entra en [Google Cloud Console](https://console.cloud.google.com/).
2. Crea un proyecto y ve a **APIs y Servicios > Credenciales**.
3. Crea un **ID de cliente de OAuth 2.0** (Tipo: Aplicación web).
4. En **Orígenes de JavaScript autorizados**, añade tu dominio (ej. `http://localhost` o `https://midominio.com`).
5. En **URIs de redireccionamiento autorizados**, añade la ruta al archivo auth (ej. `http://localhost/appdelegado/auth.php`).
6. En `config.php`, pega el ID generado:
   ```php
   define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID.apps.googleusercontent.com');
   ```

### 2. Contraseña del Panel de Profesor
Protege el panel de gestión cambiando la contraseña por defecto:
```php
define('ADMIN_PASSWORD', 'TuContraseñaSegura');
```

### 3. Configuración del Correo Electrónico (SMTP)
1. Activa la verificación en 2 pasos en tu cuenta de Google.
2. Genera una **Contraseña de Aplicación**.
3. En `config.php`, configura tus datos:
   ```php
   define('SMTP_USER', 'tu_correo@gmail.com');
   define('SMTP_PASS', 'TU_CONTRASENA_DE_APLICACION_SIN_ESPACIOS');
   define('SMTP_FROM_NAME', 'DelegaVoto');
   ```
*(La base de datos SQLite `database.sqlite` se autoconfigurará sola la primera vez).*

---

## Arquitectura y Funcionamiento Interno (Para Exposiciones)

### ¿Cómo funciona el QR y la Presencialidad?
El servidor genera un `room_token` criptográfico que se guarda en SQLite. La URL del QR proyectado se construye dinámicamente inyectando este token (`?t=TOKEN`). Al escanearlo, PHP valida si el token coincide con el de la sala activa. Si alguien teclea la URL a mano desde su casa, el sistema bloquea el acceso.

### ¿Cómo detecta los dispositivos? (Sistema Anti-Pillín)
Implementa una doble barrera:
1. **Nivel Base de Datos:** La cuenta de Google queda marcada (`has_voted = 1`) irreversiblemente tras votar.
2. **Nivel Hardware:** Se inyecta una cookie `device_voted` en el navegador. Si el alumno cierra sesión e intenta entrar con el correo de otra persona, el archivo `auth.php` intercepta esta cookie *antes* de registrar el nuevo correo y lo expulsa a `pillin.php`.
*Al reiniciar la votación desde el panel, el servidor renueva el token central, liberando todos los móviles simultáneamente para una nueva clase.*

### Stack Tecnológico
*   **Frontend:** HTML5, CSS3 moderno (Glassmorphism), JavaScript Vanilla (Canvas 3D, API Fetch).
*   **Backend:** PHP nativo estructurado.
*   **Base de Datos:** SQLite (portable y sin configuración de servidores SQL).

---

## Estructura de Archivos

*   `index.php` - Bienvenida, validación de sala y login Google.
*   `auth.php` - Verificación JWT y control anti-fraude.
*   `vote.php` - Interfaz principal de votación para el alumno.
*   `pillin.php` - Pantalla de bloqueo de seguridad.
*   `gestion_votos_secreto.php` - Panel del profesor (QR y escrutinio).
*   `api.php` - Controlador REST para votos, polling y SMTP.
*   `config.php` - Configuración global y credenciales.
*   `stars-background.js` - Motor 3D de partículas de fondo.
