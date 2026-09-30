<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Normaliza texto para comparar nombres sin tildes/mayúsculas/espacios
function normalizar_nombre($s) {
    $s = trim($s);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = str_replace(
        ['Á','É','Í','Ó','Ú','Ü','Ñ'],
        ['A','E','I','O','U','U','N'],
        $s
    );
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

// Comprobación de sesión de admin (reutilizable)
$admin_session_timeout = 1200;
$admin_ok = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true
         && isset($_SESSION['admin_auth_time'])
         && (time() - $_SESSION['admin_auth_time']) < $admin_session_timeout;

// ──────────────────────────────────────────────────────────────────────────────
// HEARTBEAT
// ──────────────────────────────────────────────────────────────────────────────
if ($action === 'heartbeat') {
    $sid = session_id();
    $now = time();
    $db->prepare("INSERT OR REPLACE INTO online_users (session_id, last_active) VALUES (?, ?)")->execute([$sid, $now]);
    $db->exec("DELETE FROM online_users WHERE last_active < " . ($now - 15));
    $count = $db->query("SELECT COUNT(*) FROM online_users")->fetchColumn();
    echo json_encode(['online' => $count]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// HTML DEL CORREO (estilo DelegaVoto)
// ──────────────────────────────────────────────────────────────────────────────
function buildResultEmailHtml($voterName, $esGanador, $winnerName, $votes) {
    $nombre  = htmlspecialchars(trim($voterName) !== '' ? trim($voterName) : 'compañero/a', ENT_QUOTES, 'UTF-8');
    $ganador = htmlspecialchars($winnerName, ENT_QUOTES, 'UTF-8');
    $votos   = (int) $votes;
    $plural  = $votos === 1 ? '' : 's';

    if ($esGanador) {
        
        $titulo = '¡Enhorabuena!';
        $cuerpo = "
            <p style='margin:0 0 16px;'>Hola <strong>$nombre</strong>,</p>
            <p style='margin:0 0 16px;'>Desde el <strong>Equipo de DelegaVoto</strong> sentimos comunicarte que... sí, te ha tocado. Has sido la persona más votada, con <strong>$votos voto$plural</strong>. </p>
            <p style='margin:0 0 16px;'>A partir de ahora eres oficialmente el/la <strong>Delegado/a de la clase</strong>. Esto incluye, sin coste adicional: aguantar quejas ajenas, hacer de mensajero/a con los profes y sobrevivir al chat de clase a las 2:00 de la mañana. Nada personal, es lo que hay. 😅</p>
            <p style='margin:0;'>Enhorabuena (o nuestro más sentido pésame, tú decides) y gracias por presentarte.</p>
        ";
        $altBody = "¡Enhorabuena! Desde el Equipo de DelegaVoto sentimos comunicarte que te ha tocado: has sido elegido/a delegado/a con $votos voto$plural. Ya sabes lo que significa... ¡suerte, la vas a necesitar! Gracias por participar.";
    } else {
        
        $titulo = 'Resultados de la Elección';
        $cuerpo = "
            <p style='margin:0 0 16px;'>Hola <strong>$nombre</strong>,</p>
            <p style='margin:0 0 16px;'>Ya tenemos resultados de la Elección de Delegado. La persona elegida ha sido <strong>$ganador</strong>, con <strong>$votos voto$plural</strong>.</p>
            <p style='margin:0 0 16px;'>En esta ocasión no te ha tocado a ti. Lo sabemos, es una lástima... o un alivio enorme, según se mire. 🙏</p>
            <p style='margin:0;'>Gracias por participar. ¡Nos vemos en la próxima votación (si hay valientes)!</p>
        ";
        $altBody = "Ya tenemos resultados. El delegado/a elegido/a ha sido: $ganador con $votos voto$plural. En esta ocasión no te ha tocado a ti. Gracias por participar.";
    }

    $html = "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;background:#f4f7f6;'>
  <div style='max-width:480px;margin:30px auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 15px 35px rgba(0,0,0,0.08);font-family:Arial,sans-serif;'>
    <div style='background:linear-gradient(135deg,#111111,#444444);padding:32px 20px;text-align:center;'>
      <div style='color:#ffffff;font-size:22px;font-weight:800;'>$titulo</div>
    </div>
    <div style='padding:30px 28px;color:#333333;font-size:14px;line-height:1.7;'>
      $cuerpo
    </div>
    <div style='padding:14px 28px 26px;text-align:center;color:#999999;font-size:11px;'>
      — El Equipo de DelegaVoto
    </div>
  </div>
</body>
</html>";

    return ['html' => $html, 'alt' => $altBody];
}

// ──────────────────────────────────────────────────────────────────────────────
// RESET DE VOTACIÓN Y COOKIES (admin)
// ──────────────────────────────────────────────────────────────────────────────

if ($action === 'admin_get_voters') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }
    $voters = $db->query("SELECT email, name, voted_for FROM users WHERE has_voted = 1")->fetchAll(PDO::FETCH_ASSOC);
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $ranking = $db->query("SELECT voted_for, COUNT(*) as count FROM users WHERE has_voted = 1 AND voted_for IS NOT NULL GROUP BY voted_for ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'voters' => $voters,
        'total_votos' => count($voters),
        'total_users' => $total_users,
        'ranking' => $ranking
    ]);
    exit;
}

if ($action === 'reset_all_devices') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }

    // Nuevo token de dispositivo → invalida cookies
    $new_token = bin2hex(random_bytes(16));
    $db->prepare("UPDATE settings SET value = ? WHERE key = 'reset_token'")->execute([$new_token]);

    // Nuevo token de sala → invalida el QR anterior (esencial para cambiar de clase)
    $new_room_token = bin2hex(random_bytes(16));
    $db->prepare("UPDATE settings SET value = ? WHERE key = 'room_token'")->execute([$new_room_token]);

    // Reiniciar base de datos por completo (borrar alumnos de la lista y votos)
    $db->exec("DELETE FROM users");
    $db->exec("DELETE FROM candidates");
    $db->exec("DELETE FROM manual_candidates");

    echo json_encode(['success' => true, 'message' => 'Votación reiniciada. Lista vaciada y nuevo QR generado.']);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// GESTIÓN DE CANDIDATOS MANUALES (admin)
// ──────────────────────────────────────────────────────────────────────────────
if ($action === 'list_manual_candidates') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }
    $rows = $db->query("SELECT id, name FROM manual_candidates ORDER BY name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

if ($action === 'add_candidate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    if ($name === '') { echo json_encode(['error' => 'Nombre vacío']); exit; }
    try {
        $db->prepare("INSERT INTO manual_candidates (name) VALUES (?)")->execute([$name]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'El candidato ya existe']);
    }
    exit;
}

if ($action === 'remove_candidate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    $db->prepare("DELETE FROM manual_candidates WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// ENVÍO DE RESULTADOS (admin)
// ──────────────────────────────────────────────────────────────────────────────
if ($action === 'send_results') {
    if (!$admin_ok) { echo json_encode(['error' => 'No autorizado']); exit; }

    // Algoritmo: gana quien más votos tenga.
    // En caso de empate se elige aleatoriamente entre los empatados (desempate justo).
    $maxVotes = $db->query("SELECT MAX(votes) FROM candidates")->fetchColumn();
    if (!$maxVotes) { echo json_encode(['error' => 'No hay votos registrados.']); exit; }

    $tiedStmt = $db->prepare("SELECT name, votes FROM candidates WHERE votes = ?");
    $tiedStmt->execute([$maxVotes]);
    $tied = $tiedStmt->fetchAll(PDO::FETCH_ASSOC);
    $winner = $tied[array_rand($tied)]; // desempate aleatorio si hay varios líderes

    $winnerNorm = normalizar_nombre($winner['name']);

    $voters = $db->query("SELECT email, name FROM users")->fetchAll(PDO::FETCH_ASSOC);

    $sent     = 0;
    $fallidos = [];

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host          = SMTP_HOST;
        $mailer->SMTPAuth      = true;
        $mailer->Username      = SMTP_USER;
        $mailer->Password      = SMTP_PASS;
        $mailer->SMTPSecure    = SMTP_SECURE === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port          = SMTP_PORT;
        $mailer->CharSet       = 'UTF-8';
        $mailer->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mailer->SMTPKeepAlive = true;
        $mailer->SMTPOptions   = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    } catch (Exception $e) {
        echo json_encode(['error' => 'No se pudo preparar el envío SMTP: ' . $e->getMessage()]);
        exit;
    }

    foreach ($voters as $v) {
        $esGanador = normalizar_nombre($v['name'] ?? '') === $winnerNorm;
        $subject   = $esGanador
            ? 'Resultados de la Elección de Delegado - ¡Enhorabuena!'
            : 'Resultados de la Elección de Delegado';

        $email = buildResultEmailHtml($v['name'] ?? '', $esGanador, $winner['name'], $winner['votes']);

        try {
            $mailer->clearAddresses();
            $mailer->addAddress($v['email']);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $email['html'];
            $mailer->AltBody = $email['alt'];
            $mailer->send();
            $sent++;
        } catch (Exception $e) {
            $fallidos[] = $v['email'] . ' (' . $mailer->ErrorInfo . ')';
        }
    }
    $mailer->smtpClose();

    echo json_encode([
        'success' => true,
        'winner'  => $winner['name'],
        'sent'    => $sent,
        'total'   => count($voters),
        'failed'  => $fallidos,
    ]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// A partir de aquí se requiere sesión de usuario normal
// ──────────────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// CANDIDATOS (usuarios registrados + candidatos manuales, sin duplicados)
if ($action === 'candidates') {
    $usersNames  = $db->query("SELECT name FROM users WHERE name IS NOT NULL AND TRIM(name) != ''")->fetchAll(PDO::FETCH_COLUMN);
    $manualNames = $db->query("SELECT name FROM manual_candidates")->fetchAll(PDO::FETCH_COLUMN);

    $seen = [];
    $all  = [];
    foreach (array_merge($usersNames, $manualNames) as $n) {
        $norm = normalizar_nombre($n);
        if (!isset($seen[$norm])) {
            $seen[$norm] = true;
            $all[]       = ['name' => $n];
        }
    }
    usort($all, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($all);
    exit;
}

// VOTAR
if ($action === 'vote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset_token = get_reset_token();

    if (isset($_COOKIE['device_voted']) && $_COOKIE['device_voted'] === $reset_token) {
        echo json_encode(['error' => 'Este dispositivo ya se ha utilizado para emitir un voto. Bloqueo de seguridad activado.']);
        exit;
    }

    $data             = json_decode(file_get_contents('php://input'), true);
    $candidateNameRaw = trim($data['candidateName'] ?? '');
    if (empty($candidateNameRaw)) { echo json_encode(['error' => 'Nombre de candidato requerido']); exit; }

    $candidateNorm = normalizar_nombre($candidateNameRaw);
    $candidateName = null;

    // Buscar en usuarios registrados
    foreach ($db->query("SELECT name FROM users WHERE name IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $rn) {
        if (normalizar_nombre($rn) === $candidateNorm) { $candidateName = normalizar_nombre($rn); break; }
    }
    // Si no está, buscar en candidatos manuales
    if ($candidateName === null) {
        foreach ($db->query("SELECT name FROM manual_candidates")->fetchAll(PDO::FETCH_COLUMN) as $mn) {
            if (normalizar_nombre($mn) === $candidateNorm) { $candidateName = normalizar_nombre($mn); break; }
        }
    }

    if ($candidateName === null) {
        echo json_encode(['error' => 'El candidato seleccionado no es válido.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT has_voted FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && $user['has_voted'] == 1) {
            $db->rollBack();
            echo json_encode(['error' => 'Tu cuenta ya ha emitido un voto. Solo se permite 1 voto.']);
            exit;
        }

        $row = $db->prepare("SELECT * FROM candidates WHERE name = ?");
        $row->execute([$candidateName]);
        if ($row->fetch()) {
            $db->prepare("UPDATE candidates SET votes = votes + 1 WHERE name = ?")->execute([$candidateName]);
        } else {
            $db->prepare("INSERT INTO candidates (name, votes) VALUES (?, 1)")->execute([$candidateName]);
        }

        $db->prepare("UPDATE users SET has_voted = 1, voted_for = ? WHERE id = ?")->execute([$candidateName, $_SESSION['user_id']]);
        $db->commit();

        // Cookie con el token actual (se invalida cuando el admin haga reset)
        setcookie('device_voted', $reset_token, time() + (86400 * 365), "/");

        echo json_encode(['success' => true, 'message' => '¡Voto registrado con éxito!']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Error interno al procesar el voto']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
