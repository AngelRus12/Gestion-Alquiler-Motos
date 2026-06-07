# Documentación del Proyecto ARUSLAT

## 1. Objetivo del proyecto
ARUSLAT es mi proyecto de un sistema web para el alquiler de motos, donde he gestionado todo el ciclo: reservas, pagos y usuarios. Lo he desarrollado en PHP con una base de datos MySQL.
- He implementado un sistema completo de registro e inicio de sesión de usuarios.
- Los usuarios pueden reservar motos, y he asegurado que el cálculo del precio se haga en el servidor para evitar manipulaciones.
- Cada usuario tiene su propio perfil para gestionar sus reservas.
- He creado un panel de administrador desde donde puedo gestionar las motos, los usuarios y hasta crear promociones.
- También he añadido una función para generar facturas en PDF de los alquileres.

## 2. Estructura del proyecto

### Carpetas principales
- `database/` : Contiene el backup SQL del proyecto (`alquilermotos.sql`).
- `libs/` : Librerías externas, especialmente `fpdf` para generación de PDF.
- `payment/` : Pasarela de pago y simulador de transacciones. 

### Archivos principales
- `index.php` : Página de inicio general.
- `catalogo.php` : Catálogo de motos disponibles con filtros y paginación.
- `detalle_moto.php` : Página de detalle y reserva de una moto.
- `registro.php` : Formulario de registro de nuevos usuarios.
- `login.php` : Formulario de inicio de sesión.
- `procesar_registro.php` : Procesa y valida el registro.
- `procesar_login.php` : Procesa la autenticación de usuario.
- `procesar_reserva.php` : Inserta la reserva en la base de datos y gestiona el flujo de pago.
- `perfil_usuario.php` : Panel de usuario con historial de reservas y estadísticas.
- `cancelar_reserva.php` : Anula una reserva pendiente.
- `admin_dashboard.php` : Panel administrativo para gestionar la plataforma.
- `generar_factura.php` : Genera una factura PDF para un alquiler.
- `funciones.php` : Funciones reutilizables y lógica de negocio central.
- `loginbd.php` : Configuración de la conexión a la base de datos.
- `logout.php` : Cierra la sesión del usuario.

## 3. Flujo de usuario

### Registro y acceso
1. El usuario accede a `registro.php` y completa el formulario.
2. `procesar_registro.php` valida:
   - Campos obligatorios.
   - Formato de DNI.
   - Contraseña segura.
   - Unicidad de email y DNI.
3. Si todo es correcto, se guarda el usuario en la tabla `usuarios`.
4. Para iniciar sesión, el usuario usa `login.php`.
5. `procesar_login.php` compara la contraseña de forma segura con `password_verify()`.
6. El sistema redirige a `index.php` o a `admin_dashboard.php` según el rol.

### Reserva de motos
1. El usuario navega por `catalogo.php`.
2. En `detalle_moto.php` selecciona fechas y método de pago.
3. `procesar_reserva.php` valida:
   - Que la sesión esté activa.
   - Que las fechas sean válidas y no se solapen con otras reservas.
   - **Importante:** El precio se calcula siempre en el servidor para evitar que se manipule desde el cliente.
4. La reserva se inserta en `alquileres` con estado `pendiente`.
5. Si elige pago online, lo redirijo a mi simulador de pasarela de pago (`pago.php`); si elige pago en tienda, la reserva queda pendiente de confirmación manual por un admin.

### Perfil de usuario
- `perfil_usuario.php` muestra:
  - Datos personales.
  - Algunas estadísticas que he calculado, como la antigüedad y el gasto total.
  - Historial de reservas.
  - Opciones para cancelar reservas pendientes.

## 4. Seguridad implementada
He adoptado un enfoque de "defensa en profundidad" para proteger la aplicación y los datos de los usuarios en varios niveles.

- **Protección de Identidad (Hashing de Contraseñas):** Nunca guardo las contraseñas en texto plano. Utilizo la función `password_hash()` de PHP con el algoritmo `PASSWORD_DEFAULT`, que me garantiza el uso del método de hashing más robusto disponible. La verificación la hago de forma segura con `password_verify()`, lo que hace imposible la ingeniería inversa de las contraseñas, incluso si alguien accediera a la base de datos.

- **Integridad de Datos (Prevención de Inyección SQL):** Todas las consultas a la base de datos que usan datos del usuario las ejecuto con **sentencias preparadas** de `MySQLi` (`mysqli_prepare`, `mysqli_stmt_bind_param`). Esta técnica neutraliza por completo los ataques de inyección SQL, ya que separa las instrucciones SQL de los datos.

- **Protección contra CSRF (Cross-Site Request Forgery):** He protegido los formularios más importantes (registro, login, cambio de contraseña) contra ataques CSRF. Para ello, uso un sistema de tokens de un solo uso (`generar_csrf_token()` y `validar_csrf_token()`) que me asegura que las solicitudes vienen de mi propia aplicación y no de un sitio malicioso.

- **Gestión Segura de Sesiones:** He implementado una gestión de sesiones más segura con mi función `start_secure_session()`. Esta configura las cookies de sesión con atributos de seguridad importantes:
  - `HttpOnly`: Previene el acceso a la cookie de sesión desde JavaScript (mitiga ataques XSS).
  - `Secure`: Asegura que la cookie solo se envíe a través de conexiones HTTPS (previene el secuestro de sesión en redes inseguras).
  - `Samesite=Lax`: Ofrece protección adicional contra ataques CSRF.

- **Control de Acceso y Autorización:** He implementado una separación estricta de roles. El acceso a rutas sensibles, como el panel de administración (`admin_dashboard.php`), está protegido por una verificación del rol del usuario en la sesión. Además, en páginas como `detalle_alquiler.php`, compruebo que un usuario solo pueda ver sus propios alquileres, a menos que sea un administrador.

- **Validación de Datos en el Servidor:** Toda la información que me llega del usuario (fechas, DNI, formato de contraseña, etc.) la valido rigurosamente en el servidor. Esto previene que se manipulen los datos en el cliente y me asegura que la información que guardo en la base de datos es consistente.

## 5. Base de datos y lógica

### Tablas principales
- `usuarios` : Usuarios con datos personales, contraseña hasheada, rol y estado.
- `motos` : Inventario de motos con precio, disponibilidad y características.
- `alquileres` : Reservas y alquileres registrados con estado y fechas.

### Lógica de negocio en PHP y SQL
El archivo `database/alquilermotos.sql` incluye:
- `actualizar_disponibilidad_motos()` : Actualiza disponibilidad según alquileres activos.
- `actualizar_estados_alquileres()` : Cambia estados según fechas.
- `actualizar_sistema_completo()` : Sincroniza estados y disponibilidad.
- `sp_gestionar_estado_alquiler()` : Lógica de estado para un alquiler específico.
- `antiguedad_usuario()` : Calcula días desde el registro.
- `calcular_precio_total()` : Calcula precio total según fechas.
- `total_gastadoo()` : Suma total gastado por usuario.

## 6. Comentarios añadidos en el código
He añadido comentarios en los archivos clave para:
- Explicar el propósito de cada archivo.
- Resumir su flujo de trabajo.
- Detallar la lógica de seguridad que he implementado.
- Facilitarme la explicación del código ante el tribunal.

## 7. Consejos para la exposición del TFG
- Explica primero la arquitectura general: cliente, servidor PHP, base de datos MySQL.
- Describe los tres perfiles: visitante, usuario registrado y administrador.
- Muestra los flujos principales:
  1. Registro -> Login -> Reserva.
  2. Panel de usuario -> Historial -> Factura.
  3. Panel admin -> Gestión de motos / promociones.
- Señala los controles de seguridad: prepared statements, hashing de contraseñas y validación server-side.
- Destaca la documentación y los comentarios como evidencia de calidad.

---

## 8. Archivos adicionales de interés
- `estilos.css`, `estilos_mobile.css` : Estilos y diseño responsivo.
- `payment/` : Ejemplos y simulaciones de pasarela de pago.
- `libs/fpdf/` : Librería externa para generar facturas en PDF.
