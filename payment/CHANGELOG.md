# Changelog

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-02-07

### ✨ Añadido

#### Funcionalidades Principales
- Simulador completo de pagos bancarios
- Soporte para Webpay Plus (Transbank Chile)
- Soporte para Mercado Pago
- Soporte para PayPal
- Soporte para Transferencias Bancarias genéricas

#### Interfaz de Usuario
- Página principal con selector de métodos de pago
- Diseño responsive con Bootstrap 5.3
- Iconos de Bootstrap Icons
- Animaciones y efectos hover
- Página de redirección automática
- Portal bancario simulado
- Página de resultado con detalles completos

#### Escenarios de Prueba
- ✅ Pago Aprobado (approved)
- ❌ Pago Rechazado (rejected)
- ⏳ Pago Pendiente (pending)
- ◀️ Pago Cancelado (cancelled)
- ⚠️ Error del Sistema (error)
- ⏰ Timeout (timeout)

#### Respuestas Simuladas
- Respuestas JSON completas por cada método de pago
- Códigos de autorización simulados
- IDs de transacción únicos
- Timestamps y fechas reales
- Datos específicos de cada proveedor (VCI, status_detail, etc.)

#### Documentación
- README.md completo en español
- Documentación inline en todos los archivos PHP
- Ejemplos de código
- Guía de instalación paso a paso
- FAQ (Preguntas Frecuentes)
- Diagramas de flujo

#### Archivos de Configuración
- `config.example.php` - Configuración de ejemplo
- `.gitignore` - Exclusiones para Git
- `includes/sessions.php` - Funciones auxiliares

#### Estilos
- CSS personalizado con animaciones
- Efectos hover mejorados
- Soporte para impresión
- Responsive design completo

### 🔧 Técnico

- Sistema de sesiones PHP para persistencia de datos
- Generación de tokens únicos
- Validación de datos de entrada
- Sanitización de datos
- Formateo de montos
- Sistema de logs opcional
- Funciones reutilizables

### 📚 Documentación

- Guía de instalación para XAMPP/WAMP/MAMP
- Guía de instalación con servidor PHP built-in
- Documentación de estructura de archivos
- Ejemplos de integración
- Documentación de API y respuestas
- Enlaces a documentación oficial de proveedores

### 🎨 Diseño

- Interfaz moderna y limpia
- Paleta de colores consistente
- Gradientes y sombras suaves
- Animaciones CSS
- Loading spinners
- Badges y etiquetas informativas

---

## [Unreleased] - Próximas versiones

### Planeado para v1.1

- [ ] Agregar Stripe como método de pago
- [ ] Agregar Khipu (Chile)
- [ ] Agregar Flow (Chile)
- [ ] Base de datos SQLite opcional
- [ ] Panel de administración básico
- [ ] Logs persistentes en archivos
- [ ] Sistema de webhooks simulados
- [ ] Exportar transacciones a CSV/Excel
- [ ] Multi-idioma (inglés, portugués)

### Planeado para v1.2

- [ ] Modo de prueba automatizado
- [ ] Generador de escenarios de prueba
- [ ] Simulación de delays de red
- [ ] Simulación de respuestas parciales
- [ ] Estadísticas de uso
- [ ] Historial de transacciones con búsqueda

### Planeado para v2.0

- [ ] API REST completa
- [ ] Autenticación con API keys
- [ ] Webhooks reales simulados
- [ ] Base de datos MySQL/PostgreSQL
- [ ] Tests automatizados (PHPUnit)
- [ ] Docker container
- [ ] CI/CD con GitHub Actions
- [ ] Dashboard con gráficos
- [ ] Generador de reportes

---

## Tipos de Cambios

- **✨ Añadido**: Para nuevas funcionalidades
- **🔧 Cambiado**: Para cambios en funcionalidades existentes
- **⚠️ Deprecado**: Para funcionalidades que serán removidas
- **🗑️ Removido**: Para funcionalidades removidas
- **🐛 Corregido**: Para corrección de bugs
- **🔒 Seguridad**: Para mejoras de seguridad

---

## Versionado

Este proyecto usa [Semantic Versioning](https://semver.org/):

- **MAJOR**: Cambios incompatibles con versiones anteriores
- **MINOR**: Nuevas funcionalidades compatibles con versiones anteriores
- **PATCH**: Correcciones de bugs compatibles con versiones anteriores

Formato: `MAJOR.MINOR.PATCH` (ejemplo: 1.2.3)

---

## Cómo Contribuir

Si quieres contribuir al proyecto:

1. Revisa este CHANGELOG para entender los cambios recientes
2. Verifica [Unreleased] para ver qué está planeado
3. Crea un issue para proponer nuevas funcionalidades
4. Envía tu pull request con descripción detallada
5. Actualiza este CHANGELOG con tus cambios

---

## Enlaces

- [Repositorio](https://github.com/tu-usuario/bank_simulator)
- [Issues](https://github.com/tu-usuario/bank_simulator/issues)
- [Documentación](README.md)
- [Licencia](LICENSE)

---

**Última actualización**: 7 de febrero de 2024
