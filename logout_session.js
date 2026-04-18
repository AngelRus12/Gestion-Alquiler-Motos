// Script para cerrar sesión automáticamente cuando se cierra la pestaña
window.addEventListener('beforeunload', function(event) {
    // Crear una petición AJAX síncrona para cerrar la sesión
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'logout_session.php', false); // false = síncrono
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('close_tab=1');

    // Nota: En algunos navegadores, las peticiones síncronas en beforeunload pueden ser bloqueadas
    // Esta es una aproximación, pero no es 100% confiable
});

// También cerrar sesión cuando se recarga la página (F5)
window.addEventListener('unload', function(event) {
    // Similar al beforeunload, pero menos confiable
    navigator.sendBeacon('logout_session.php', 'close_tab=1');
});