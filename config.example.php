<?php
session_start();

// --- Credenciales y Seguridad ---
define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID_AQUI.apps.googleusercontent.com');
define('ADMIN_PASSWORD', 'TuContraseñaSegura');

// --- Configuración SMTP (Gmail) ---
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_SECURE',    'tls');
define('SMTP_USER',      'tu_correo@gmail.com');
define('SMTP_PASS',      'TU_CONTRASENA_DE_APLICACION_SIN_ESPACIOS');
define('SMTP_FROM_NAME', 'DelegaVoto');

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// --- Base de Datos y Tablas Automáticas ---
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        google_id TEXT UNIQUE,
        email TEXT,
        has_voted INTEGER DEFAULT 0,
        voted_for TEXT
    )");
    try { $db->exec("ALTER TABLE users ADD COLUMN voted_for TEXT"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN name TEXT"); }     catch(Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        votes INTEGER DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS online_users (
        session_id TEXT PRIMARY KEY,
        last_active INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS manual_candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    )");

    $tokenRow = $db->query("SELECT value FROM settings WHERE key = 'reset_token'")->fetch();
    if (!$tokenRow) {
        $tok = bin2hex(random_bytes(16));
        $db->prepare("INSERT INTO settings (key, value) VALUES ('reset_token', ?)")->execute([$tok]);
    }
} catch (PDOException $e) {
    die("Error de Base de Datos: " . $e->getMessage());
}

function get_reset_token() {
    global $db;
    $row = $db->query("SELECT value FROM settings WHERE key = 'reset_token'")->fetch();
    return $row ? $row['value'] : '';
}

function normalizar_nombre($nombre) {
    $nombre = mb_strtolower(trim($nombre), 'UTF-8');
    $nombre = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü'],
        ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
        $nombre
    );
    return preg_replace('/[^a-z0-9]/', '', $nombre);
}
