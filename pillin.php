<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>¡Alto ahí!</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <canvas id="stars-canvas"></canvas>
    <div class="login-container">
        <div class="glass-panel" style="border-color: #e74c3c; box-shadow: 0 10px 30px rgba(231, 76, 60, 0.15);">
            <div class="busted-icon" style="font-size: 60px; margin-bottom: 10px; animation: shake 0.5s ease-in-out infinite alternate;"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
            <h1 class="title" style="background: linear-gradient(90deg, #e74c3c, #c0392b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">¡¿Dónde vas, pillín?!</h1>
            <p class="subtitle" style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Hemos detectado que ya has emitido tu voto desde este dispositivo o con esta cuenta.
            </p>
            <div class="device-blocked" style="margin-bottom: 25px; background: rgba(231,76,60,0.1); border: 1px solid #e74c3c; border-radius: 10px; padding: 12px; color: #c0392b; font-size: 12px; line-height: 1.4;">
                No intentes alterar los resultados. El voto doble está estrictamente prohibido y bloqueado por seguridad.
            </div>
            
            <a href="logout.php" class="btn-vote" style="background: linear-gradient(45deg, #e74c3c, #c0392b); text-decoration: none; box-shadow: 0 8px 15px rgba(231, 76, 60, 0.3);">
                <span class="btn-text">Entendido, me porto bien</span>
            </a>
        </div>
    </div>
        <script src="stars-background.js"></script>
    <style>
        @keyframes shake {
            0% { transform: rotate(-10deg); }
            100% { transform: rotate(10deg); }
        }
    </style>
</body>
</html>
