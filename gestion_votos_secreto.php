<?php
require 'config.php';

$max_attempts    = 5;
$lockout_seconds = 300;
$session_timeout = 1200;

if (!isset($_SESSION['admin_attempts']))      $_SESSION['admin_attempts'] = 0;
if (!isset($_SESSION['admin_lockout_until'])) $_SESSION['admin_lockout_until'] = 0;

$auth = false;
if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
    if (isset($_SESSION['admin_auth_time']) && (time() - $_SESSION['admin_auth_time']) < $session_timeout) {
        $auth = true;
        $_SESSION['admin_auth_time'] = time();
    } else {
        unset($_SESSION['admin_auth'], $_SESSION['admin_auth_time']);
    }
}

$locked   = time() < $_SESSION['admin_lockout_until'];
$errorMsg = '';

if (!$auth && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($locked) {
        $mins     = (int) ceil(($_SESSION['admin_lockout_until'] - time()) / 60);
        $errorMsg = "Demasiados intentos fallidos. Inténtalo de nuevo en $mins minuto(s).";
    } elseif (hash_equals(ADMIN_PASSWORD, (string) $_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_auth']      = true;
        $_SESSION['admin_auth_time'] = time();
        $_SESSION['admin_attempts']  = 0;
        $auth = true;
    } else {
        sleep(1);
        $_SESSION['admin_attempts']++;
        if ($_SESSION['admin_attempts'] >= $max_attempts) {
            $_SESSION['admin_lockout_until'] = time() + $lockout_seconds;
            $_SESSION['admin_attempts']      = 0;
            $mins     = (int) ceil($lockout_seconds / 60);
            $errorMsg = "Demasiados intentos fallidos. Inténtalo de nuevo en $mins minuto(s).";
        } else {
            $errorMsg = 'Contraseña incorrecta.';
        }
    }
}

if (!$auth) {
    $disabled = ($locked ? time() < $_SESSION['admin_lockout_until'] : false) ? 'disabled' : '';
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Acceso Restringido</title><link rel="stylesheet" href="style.css"></head><body class="login-body"><canvas id="stars-canvas"></canvas><div class="glass-panel"><h2 class="title" style="margin-bottom:20px;">Acceso Confidencial</h2>'
        . ($errorMsg ? '<p style="color:#e74c3c;font-size:13px;margin-bottom:10px;">' . htmlspecialchars($errorMsg) . '</p>' : '')
        . '<form method="POST"><input type="password" name="password" placeholder="Clave de acceso" style="margin-bottom:15px;" ' . $disabled . '><button type="submit" class="btn-vote" ' . $disabled . '>Entrar</button></form></div><script src="stars-background.js"></script></body></html>';
    exit;
}

$voters      = $db->query("SELECT email, voted_for, name FROM users WHERE has_voted = 1")->fetchAll(PDO::FETCH_ASSOC);
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$ranking = $db->query("SELECT voted_for, COUNT(*) as count FROM users WHERE has_voted = 1 AND voted_for IS NOT NULL GROUP BY voted_for ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_votos = count($voters);
$room_token_row = $db->query("SELECT value FROM settings WHERE key = 'room_token'")->fetch();
$room_token = $room_token_row ? $room_token_row['value'] : '';
$qr_url      = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/index.php?t=" . $room_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gestión — DelegaVoto</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { overflow-y: auto !important; display: block; padding-bottom: 60px; }

        .admin-panel { max-width: 860px; width: 100%; margin: 40px auto; padding: 0 16px; position: relative; z-index: 5; }
        .admin-panel > h1 { text-align: center; margin-bottom: 28px; }

        .admin-box {
            background: rgba(255,255,255,0.97);
            padding: 20px 22px;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.07);
            margin-bottom: 20px;
        }
        .admin-box h3 { color: #111111; margin-bottom: 14px; font-size: 15px; }

        /* ── Botón de reset ── */
        .btn-reset {
            background: linear-gradient(45deg, #e67e22, #e74c3c);
            color: white; border: none; padding: 13px 22px; border-radius: 30px;
            font-size: 15px; font-weight: 800; cursor: pointer; width: 100%;
            box-shadow: 0 6px 14px rgba(231,76,60,0.3); transition: 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-reset:hover { transform: translateY(-1px); }
        .btn-reset:active { transform: scale(0.97); }

        /* ── QR collapsible ── */
        .qr-toggle {
            width: 100%; background: none; border: none; cursor: pointer;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0; font-size: 15px; font-weight: 700; color: #333;
        }
        .qr-toggle .arrow { transition: transform 0.3s; font-size: 12px; color: #111111; }
        .qr-toggle.open .arrow { transform: rotate(180deg); }
        .qr-body { overflow: hidden; max-height: 0; transition: max-height 0.4s ease; }
        .qr-body.open { max-height: 400px; }
        .qr-inner { padding-top: 16px; text-align: center; }
        .qr-inner img { width: 200px; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,0.15); }
        .btn-fullscreen {
            display: inline-block; margin-top: 12px;
            background: linear-gradient(45deg, #111111, #444444);
            color: white; border: none; padding: 9px 20px; border-radius: 20px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.25); transition: 0.25s;
        }
        .btn-fullscreen:hover { transform: translateY(-1px); }

        /* ── Overlay pantalla completa QR ── */
        #qrOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(255, 255, 255, 0.5); 
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 9999;
            align-items: center; justify-content: center; flex-direction: column;
            cursor: pointer;
        }
        #qrOverlay img { 
            width: min(70vmin, 400px); 
            border-radius: 24px; 
            padding: 24px;
            background: #ffffff;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1); 
        }
        #qrOverlay p { 
            color: #333; 
            margin-top: 24px; 
            font-size: 14px; 
            font-family: 'Poppins', sans-serif; 
            font-weight: 500;
            background: rgba(255,255,255,0.7);
            padding: 8px 18px;
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        /* ── Tabla de votos ── */
        .voters-scroll { max-height: 280px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #eee; padding: 9px 6px; text-align: left; }
        th { font-weight: 700; color: #111111; }
        td { word-break: break-all; }

        /* ── Candidatos manuales ── */
        .candidate-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.2);
            border-radius: 20px; padding: 5px 12px; margin: 4px; font-size: 13px; font-weight: 600; color: #5a1ab5;
        }
        .candidate-chip button {
            background: none; border: none; cursor: pointer; color: #e74c3c;
            font-size: 15px; line-height: 1; padding: 0; font-weight: 800;
        }
        #candidatesWrap { min-height: 36px; margin-bottom: 12px; }
        .add-row { display: flex; gap: 10px; }
        .add-row input {
            flex: 1; padding: 10px 14px; border-radius: 10px;
            border: 2px solid rgba(0,0,0,0.2); font-size: 14px; font-weight: 600; outline: none;
            transition: 0.2s;
        }
        .add-row input:focus { border-color: #111111; }
        .btn-add {
            background: linear-gradient(45deg, #111111, #444444); color: white;
            border: none; padding: 10px 18px; border-radius: 10px; font-size: 14px;
            font-weight: 700; cursor: pointer; white-space: nowrap;
        }

        /* ── Enviar resultados ── */
        .btn-send {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white; border: none; padding: 13px 22px; border-radius: 30px;
            font-size: 15px; font-weight: 800; cursor: pointer; width: 100%;
            box-shadow: 0 6px 14px rgba(46,204,113,0.3); transition: 0.3s;
        }
        .btn-send:disabled { opacity: 0.5; cursor: default; }

        @media(min-width: 768px) {
            .admin-box { padding: 24px 28px; }
            .admin-box h3 { font-size: 16px; }
        }
    </style>
</head>
<body>
    <canvas id="stars-canvas"></canvas>

    <!-- Overlay QR pantalla completa -->
    <div id="qrOverlay" onclick="closeQrOverlay()">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?= urlencode($qr_url) ?>&color=111111&bgcolor=ffffff&margin=0" alt="QR">
        <p>Toca en cualquier lugar para cerrar</p>
    </div>

    <div class="admin-panel">
        <h1 class="title" style="font-size:28px;">Gestión de Votos</h1>

        <!-- ── RESET ── -->
        <div class="admin-box">
            <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 8px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>Reiniciar Votación</h3>
            <p style="font-size:12px;color:#888;margin-bottom:14px;">
                Borra todos los votos y genera un nuevo token de dispositivo. Las cookies guardadas en los móviles de los alumnos quedan <strong>invalidadas automáticamente</strong>, sin necesidad de ir dispositivo a dispositivo.
            </p>
            <button class="btn-reset" id="btnResetAll"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 6px;"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>Reiniciar votación y limpiar cookies</button>
            <p id="resetMsg" style="font-size:12px;color:#666;margin-top:10px;min-height:16px;"></p>
        </div>

        <!-- ── QR COLLAPSIBLE ── -->
        <div class="admin-box">
            <button class="qr-toggle" id="qrToggle" onclick="toggleQr()">
                <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><path d="M8 12h8"></path><path d="M12 8v8"></path></svg> Código de Acceso (QR)</span>
                <span class="arrow">▼</span>
            </button>
            <div class="qr-body" id="qrBody">
                <div class="qr-inner">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($qr_url) ?>&color=111111&bgcolor=ffffff&margin=0" style="border-radius:12px; padding:12px; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.06);" alt="QR Acceso">
                    <br>
                    <button class="btn-fullscreen" onclick="openQrOverlay()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg> Ver en pantalla completa</button>
                    <p style="font-size:11px;color:#999;margin-top:8px;">Proyecta el QR a pantalla completa para que los alumnos lo escaneen.</p>
                </div>
            </div>
        </div>

                <!-- ── RANKING EN DIRECTO ── -->
        <div class="admin-box">
            <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 8px;"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>Escrutinio en Directo</h3>
            <div id="rankingBody">
                <?php if (empty($ranking)): ?>
                    <p style="text-align:center;color:#bbb;font-size:13px;">Esperando votos...</p>
                <?php else: ?>
                    <?php foreach ($ranking as $r): ?>
                        <?php 
                            $pct = ($total_votos > 0) ? round(($r['count'] / $total_votos) * 100) : 0; 
                            $safeName = htmlspecialchars($r['voted_for']);
                        ?>
                        <div style="margin-bottom: 12px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color:#333; margin-bottom:6px;">
                                <span><?= $safeName ?></span>
                                <span><?= $r['count'] ?> votos (<?= $pct ?>%)</span>
                            </div>
                            <div style="background:#f0f0f0; border-radius:10px; height:8px; overflow:hidden;">
                                <div style="background:linear-gradient(90deg, #111, #555); width:<?= $pct ?>%; height:100%; border-radius:10px; transition:width 0.5s ease;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

<!-- ── VOTOS REGISTRADOS ── -->
        <div class="admin-box">
            <h3 id="votosTitle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 8px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 18h6"/><path d="M9 10h6"/></svg>Votos Registrados (<?= $total_votos ?>)</h3>
            <div class="voters-scroll">
                <table>
                    <thead><tr><th>Alumno</th><th>Ha votado a</th></tr></thead>
                    <tbody id="votersBody">
                        <?php foreach ($voters as $v): ?>
                        <tr>
                            <td>
                                <?php
                                    $n = trim($v['name'] ?? '');
                                    echo htmlspecialchars($n !== '' ? "$n <{$v['email']}>" : $v['email']);
                                ?>
                            </td>
                            <td><?= htmlspecialchars($v['voted_for'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($total_votos === 0): ?>
                        <tr><td colspan="2" style="text-align:center;color:#bbb;">Aún no hay votos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── CANDIDATOS MANUALES ── -->
        <div class="admin-box">
            <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 8px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Candidatos Preestablecidos</h3>
            <p style="font-size:12px;color:#888;margin-bottom:12px;">
                Añade aquí los nombres de los alumnos que quieres que aparezcan en el desplegable de votación, sin que tengan que iniciar sesión primero.
            </p>
            <div id="candidatesWrap"></div>
            <div class="add-row">
                <input type="text" id="newCandidateName" placeholder="Nombre del candidato..." maxlength="80">
                <button class="btn-add" onclick="addCandidate()">+ Añadir</button>
            </div>
            <p id="candidateMsg" style="font-size:12px;color:#e74c3c;margin-top:8px;min-height:16px;"></p>
        </div>

        <!-- ── ENVIAR RESULTADOS ── -->
        <div class="admin-box">
            <button class="btn-send" id="btnSendResults"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><path d="M22 2L11 13"></path><path d="M22 2L15 22L11 13L2 9L22 2Z"></path></svg> Cerrar Votación y Enviar Resultados</button>
            <p id="sendMsg" style="font-size:12px;color:#666;margin-top:10px;min-height:16px;"></p>
        </div>
    </div>

    <script>
        // Partículas (particles.js loaded below)

        // ── QR ──
        function toggleQr() {
            const body   = document.getElementById('qrBody');
            const toggle = document.getElementById('qrToggle');
            const open   = body.classList.toggle('open');
            toggle.classList.toggle('open', open);
        }
        function openQrOverlay() {
            const o = document.getElementById('qrOverlay');
            o.style.display = 'flex';
        }
        function closeQrOverlay() {
            document.getElementById('qrOverlay').style.display = 'none';
        }

        // ── RESET ──
        document.getElementById('btnResetAll').addEventListener('click', async () => {
            if (!confirm('¿Seguro que quieres reiniciar la votación?\nSe borrarán todos los votos y se invalidarán las cookies de todos los dispositivos.')) return;
            const btn = document.getElementById('btnResetAll');
            const msg = document.getElementById('resetMsg');
            btn.disabled = true; btn.style.opacity = '0.5';
            msg.style.color = '#666'; msg.innerText = 'Reiniciando...';
            try {
                const res  = await fetch('api.php?action=reset_all_devices', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    msg.style.color = '#2ecc71';
                    msg.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;transform:translateY(-1px);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>' + data.message;
                    setTimeout(() => location.reload(), 1800);
                } else {
                    msg.style.color = '#e74c3c';
                    msg.innerText = 'Error: ' + data.error;
                    btn.disabled = false; btn.style.opacity = '1';
                }
            } catch(e) {
                msg.style.color = '#e74c3c'; msg.innerText = 'Error de conexión.';
                btn.disabled = false; btn.style.opacity = '1';
            }
        });

        // ── CANDIDATOS MANUALES ──
        let manualCandidates = [];

        async function loadCandidates() {
            try {
                const res  = await fetch('api.php?action=list_manual_candidates');
                manualCandidates = await res.json();
                renderCandidates();
            } catch(e) { console.error(e); }
        }

        function renderCandidates() {
            const wrap = document.getElementById('candidatesWrap');
            if (!manualCandidates.length) {
                wrap.innerHTML = '<p style="color:#bbb;font-size:12px;">Sin candidatos preestablecidos aún.</p>';
                return;
            }
            wrap.innerHTML = manualCandidates.map(c =>
                `<span class="candidate-chip">
                    ${escHtml(c.name)}
                    <button onclick="removeCandidate(${c.id})" title="Eliminar">×</button>
                </span>`
            ).join('');
        }

        function escHtml(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function addCandidate() {
            const input = document.getElementById('newCandidateName');
            const msg   = document.getElementById('candidateMsg');
            const name  = input.value.trim();
            if (!name) { msg.innerText = 'Escribe un nombre primero.'; return; }
            msg.innerText = '';
            try {
                const res  = await fetch('api.php?action=add_candidate', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();
                if (data.success) {
                    input.value = '';
                    loadCandidates();
                } else {
                    msg.innerText = data.error || 'Error al añadir.';
                }
            } catch(e) { msg.innerText = 'Error de conexión.'; }
        }

        async function removeCandidate(id) {
            try {
                await fetch('api.php?action=remove_candidate', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                loadCandidates();
            } catch(e) { console.error(e); }
        }

        // Enter en el input de candidato
        document.addEventListener('DOMContentLoaded', () => {
            loadCandidates();
            document.getElementById('newCandidateName').addEventListener('keydown', e => {
                if (e.key === 'Enter') addCandidate();
            });
        });

        // ── ENVIAR RESULTADOS ──
        document.getElementById('btnSendResults').addEventListener('click', async () => {
            if (!confirm('¿Cerrar la votación y enviar los correos a todos los participantes?')) return;
            const btn = document.getElementById('btnSendResults');
            const msg = document.getElementById('sendMsg');
            btn.disabled = true; btn.style.opacity = '0.5';
            msg.innerText = 'Calculando resultados y enviando correos...';
            try {
                const res  = await fetch('api.php?action=send_results', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    let extra = data.failed && data.failed.length
                        ? ` (${data.failed.length} correo(s) fallido(s): ${data.failed.join(', ')})`
                        : '';
                    msg.innerHTML = `<span style="color:#2ecc71;font-weight:bold;">¡Ganador: ${data.winner}! ${data.sent}/${data.total} correos enviados.</span><br><span style="color:#e67e22;">${extra}</span>`;
                } else {
                    msg.innerHTML = `<span style="color:#e74c3c;">Error: ${data.error}</span>`;
                    btn.disabled = false; btn.style.opacity = '1';
                }
            } catch(e) {
                msg.innerHTML = `<span style="color:#e74c3c;">Error de conexión.</span>`;
                btn.disabled = false; btn.style.opacity = '1';
            }
        });
    </script>
        <script src="stars-background.js"></script>
        <script>
        // ── AUTO-REFRESH VOTOS Y RANKING ──
        setInterval(async () => {
            try {
                const res = await fetch('api.php?action=admin_get_voters');
                const data = await res.json();
                if(data.success) {
                    // Update Title
                    const title = document.getElementById('votosTitle');
                    if (title) {
                        title.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 8px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 18h6"/><path d="M9 10h6"/></svg>Votos Registrados (${data.total_votos} / ${data.total_users} alumnos)`;
                    }
                    
                    // Update Ranking
                    const rBody = document.getElementById('rankingBody');
                    if(rBody) {
                        if(data.ranking && data.ranking.length > 0) {
                            rBody.innerHTML = data.ranking.map(r => {
                                const pct = data.total_votos > 0 ? Math.round((r.count / data.total_votos) * 100) : 0;
                                const safeName = (r.voted_for || '—').replace(/</g, "&lt;").replace(/>/g, "&gt;");
                                return `
                                <div style="margin-bottom: 12px;">
                                    <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color:#333; margin-bottom:6px;">
                                        <span>${safeName}</span>
                                        <span>${r.count} votos (${pct}%)</span>
                                    </div>
                                    <div style="background:#f0f0f0; border-radius:10px; height:8px; overflow:hidden;">
                                        <div style="background:linear-gradient(90deg, #111, #555); width:${pct}%; height:100%; border-radius:10px; transition:width 0.5s ease;"></div>
                                    </div>
                                </div>`;
                            }).join('');
                        } else {
                            rBody.innerHTML = '<p style="text-align:center;color:#bbb;font-size:13px;">Esperando votos...</p>';
                        }
                    }

                    // Update Voters Table
                    const tbody = document.getElementById('votersBody');
                    if(tbody) {
                        if(data.total_votos === 0) {
                            tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#bbb;">Aún no hay votos.</td></tr>';
                        } else {
                            tbody.innerHTML = data.voters.map(v => {
                                const n = v.name ? v.name.trim() : '';
                                const safeN = n.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                                const safeEmail = (v.email || '').replace(/</g, "&lt;").replace(/>/g, "&gt;");
                                const safeVoted = (v.voted_for || '—').replace(/</g, "&lt;").replace(/>/g, "&gt;");
                                const display = safeN ? `${safeN} &lt;${safeEmail}&gt;` : safeEmail;
                                return `<tr><td>${display}</td><td>${safeVoted}</td></tr>`;
                            }).join('');
                        }
                    }
                }
            } catch (e) {
                console.error("Error updating voters:", e);
            }
        }, 2000);
        </script>
</body>
</html>
