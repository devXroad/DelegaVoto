<?php
require 'config.php';

$room_token_row = $db->query("SELECT value FROM settings WHERE key = 'room_token'")->fetch();
$current_room_token = $room_token_row ? $room_token_row['value'] : null;

if (isset($_GET['t'])) {
    if ($_GET['t'] === $current_room_token) {
        $_SESSION['allowed_to_vote'] = true;
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: vote.php");
    exit;
}

$allowed = isset($_SESSION['allowed_to_vote']) && $_SESSION['allowed_to_vote'] === true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Elección de Delegado - Iniciar Sesión</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="login-body">
    <canvas id="stars-canvas"></canvas>
    <div class="login-container">
        <div class="glass-panel">
            <div class="ai-voice-core">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
            <h1 class="title">Elección de Delegado</h1>
            <p class="subtitle">Tu voz, tu voto. Sistema seguro.</p>
            
            <?php if (!$allowed): ?>
                <div style="padding: 15px; margin-top: 15px; background: rgba(231,76,60,0.1); border-radius: 12px; border: 1px solid rgba(231,76,60,0.3); color: #c0392b; font-weight: 600; font-size: 14px;">
                    <div style="display:flex; align-items:center; gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg><span>Escanea el código QR proyectado en clase para poder unirte.</span></div>
                </div>
            <?php else: ?>
                <div class="action-area">
                    <div id="g_id_onload"
                         data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-login_uri="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/auth.php" ?>"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="pill"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left">
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error'])): ?>
                <p class="error-msg">Error al iniciar sesión.</p>
            <?php endif; ?>
            <p class="info-text">Solo se permite 1 voto por persona y dispositivo.</p>
        </div>
    </div>
        <script src="stars-background.js"></script>
</body>
</html>
