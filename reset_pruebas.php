<?php
// 1. Borrar la cookie de dispositivo forzando su caducidad en el pasado
setcookie('device_voted', '', time() - 3600, '/');
unset($_COOKIE['device_voted']);

// 2. Destruir cualquier sesión iniciada
session_start();
session_destroy();

// 3. Borrar físicamente el archivo de la base de datos para resetear todos los votos
$dbFile = __DIR__ . '/database.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset de Pruebas</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <canvas id="stars-canvas"></canvas>
    <div class="glass-panel" style="text-align: center;">
        <h1 class="title" style="color: #2ecc71;">¡Todo Reseteado! <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-left: 8px; transform: translateY(-3px);"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></h1>
        <p style="color: #666; font-size: 14px; margin-top: 15px; margin-bottom: 25px;">
            Se ha borrado la base de datos completa y se ha eliminado el bloqueo de tu dispositivo. Ya puedes volver a probar desde cero.
        </p>
        <a href="index.php" class="btn-vote" style="text-decoration: none;">Ir a Iniciar Sesión</a>
    </div>
        <script src="stars-background.js"></script>
</body>
</html>
