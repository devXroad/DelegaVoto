<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $db->prepare("SELECT has_voted FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$reset_token = get_reset_token();
$has_voted   = ($user['has_voted'] == 1)
            || (isset($_COOKIE['device_voted']) && $_COOKIE['device_voted'] === $reset_token);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>DelegaVoto - Votar</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <script>
        const USER_HAS_VOTED = <?= $has_voted ? 'true' : 'false' ?>;
    </script>
</head>
<body class="vote-body">
    <canvas id="stars-canvas"></canvas>

    <div class="nav-bar">
        <div class="user-info"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px; transform: translateY(-1px);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> <?= htmlspecialchars(explode('@', $_SESSION['email'])[0]) ?></div>
        <div class="online-indicator" id="onlineIndicator"><svg width="10" height="10" viewBox="0 0 24 24" fill="#2ecc71" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="8"></circle></svg> Conectando...</div>
        <a href="logout.php" class="btn-logout">Salir</a>
    </div>

    <div class="main-container" id="mainContainer">
        <div class="voting-section <?= $has_voted ? 'hidden' : '' ?>" id="votingSection">
            <h2 class="title">¿Quién será el delegado?</h2>
            <p class="subtitle">Elige a tu candidato/a de la lista</p>

            <div style="background: rgba(0, 0, 0, 0.03); border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 12px; padding: 12px; margin-bottom: 18px; font-size: 12px; color: #555; font-weight: 500; display: flex; align-items: flex-start; text-align: left; gap: 10px; line-height: 1.4;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>La lista se actualiza sola al entrar tus compañeros. <strong>Espera a que esté quien quieras votar</strong> si no está todavía.</span>
            </div>

            <div class="input-wrapper">
                <select id="candidateSelect" class="candidate-select">
                    <option value="" selected disabled>Selecciona un/a alumno/a...</option>
                </select>
            </div>

            <button id="btnVote" class="btn-vote">
                <span class="btn-text">EMITIR VOTO</span>
                <div class="btn-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:8px;"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path><path d="M18 5l-7 7-3-3"></path></svg></div>
            </button>
            <div id="errorMessage" class="error-msg"></div>
        </div>

        <div class="success-section <?= !$has_voted ? 'hidden' : '' ?>" id="successSection">
            <div class="check-animation">
                <div class="check-circle">
                    <svg class="check-mark" viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 27 L22 35 L38 17" />
                    </svg>
                </div>
            </div>
            <h1 class="success-title">¡Voto Protegido!</h1>
            <p class="success-subtitle">Tu voto se ha registrado. Puedes salir de forma segura.</p>
        </div>
    </div>

    <div class="urna-container hidden" id="urnaContainer">
        <div class="urna">
            <div class="urna-top"></div>
            <div class="urna-front"><div class="urna-slot"></div></div>
            <div class="urna-left"></div>
            <div class="urna-right"></div>
            <div class="urna-bottom"></div>
        </div>
        <div class="ticket" id="voteTicket">VOTO</div>
    </div>

    <script src="app.js"></script>
        <script src="stars-background.js"></script>
</body>
</html>
