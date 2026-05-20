# Documentación del Proyecto ARUSLAT

## 1. Objetivo del proyecto
ARUSLAT es un sistema web de alquiler de motos para la gestión de reservas, pagos y usuarios. Está desarrollado en PHP con MySQL y permite:
- Registro e inicio de sesión de usuarios.
- Reserva de motos con cálculo seguro del precio.
- Gestión de reservas y perfil de usuario.
- Panel de administrador para gestionar motos, usuarios y promociones.
- Generación de facturas en PDF.

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
5. `procesar_login.php` compara la contraseña con `password_verify()`.
6. El sistema redirige a `index.php` o a `admin_dashboard.php` según el rol.

### Reserva de motos
1. El usuario navega por `catalogo.php`.
2. En `detalle_moto.php` selecciona fechas y método de pago.
3. `procesar_reserva.php` valida:
   - Sesión activa.
   - Fechas válidas y sin solapamientos.
   - Cálculo seguro del precio en el servidor.
4. La reserva se inserta en `alquileres` con estado `pendiente`.
5. Si elige pago online, se redirige a `pago.php`; si elige pago en tienda, la reserva se confirma de inmediato.

### Perfil de usuario
- `perfil_usuario.php` muestra:
  - Datos personales.
  - Estadísticas de antigüedad y gasto total.
  - Historial de reservas.
  - Opciones para cancelar reservas pendientes.

## 4. Seguridad implementada
- Uso de `session_start()` para manejar sesiones.
- Uso de consultas preparadas `mysqli_prepare()` para evitar inyección SQL.
- Contraseñas almacenadas con `password_hash()`.
- Validación de datos en el servidor para fechas, DNI y formato de contraseña.
- Escritura de comentarios y mensajes de error para guiar al usuario.
- Puntos de mejora: fortalecer CSRF en formularios adicionales.

## 5. Base de datos y lógica

### Tablas principales
- `usuarios` : Usuarios con datos personales, contraseña hasheada, rol y estado.
- `motos` : Inventario de motos con precio, disponibilidad y características.
- `alquileres` : Reservas y alquileres registrados con estado y fechas.
- `promociones` : Mensajes promocionales visibles en la página de inicio.

### Stored procedures y funciones SQL
El archivo `database/alquilermotos.sql` incluye:
- `actualizar_disponibilidad_motos()` : Actualiza disponibilidad según alquileres activos.
- `actualizar_estados_alquileres()` : Cambia estados según fechas.
- `actualizar_sistema_completo()` : Sincroniza estados y disponibilidad.
- `sp_gestionar_estado_alquiler()` : Lógica de estado para un alquiler específico.
- `antiguedad_usuario()` : Calcula días desde el registro.
- `calcular_precio_total()` : Calcula precio total según fechas.
- `total_gastadoo()` : Suma total gastado por usuario.

## 6. Comentarios añadidos en el código
Se han añadido comentarios de cabecera en los archivos clave para:
- Indicar el propósito de cada archivo.
- Resumir el flujo principal.
- Explicar la lógica de seguridad.
- Facilitar su explicación ante el tribunal.

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
