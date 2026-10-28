<?php
/**
 * ============================================================================
 * DATABASE CONFIGURATION & CONNECTION (PDO - MySQL / TiDB Cloud)
 * ============================================================================
 */

// Load .env variables if not already loaded in environment
function loadEnv($envFile = null) {
    if ($envFile === null) {
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    }
    if (!file_exists($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip surrounding quotes
            if (preg_match('/^"(.+)"$/', $value, $matches) || preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Initial environment load
loadEnv();

/**
 * Get or create PDO Database Connection
 *
 * @return PDO
 * @throws Exception
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    loadEnv();

    $host = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
    $port = getenv('DB_PORT') ?: 4000;
    $dbname = getenv('DB_NAME') ?: 'test';
    $user = getenv('DB_USER') ?: '3dArsb3jCD8JmQU.root';
    $pass = getenv('DB_PASS') ?: 'AlphaTrader@2026#Pass';
    $sslCa = getenv('DB_SSL_CA') ?: 'cacert.pem';

    // Resolve SSL CA path
    $caPath = $sslCa;
    if (!file_exists($caPath)) {
        $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . $sslCa;
        if (file_exists($candidate)) {
            $caPath = $candidate;
        }
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (file_exists($caPath)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[Database Connection Error] " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

/**
 * ============================================================================
 * TABLE NAME RESOLUTION HELPERS (DYNAMIC / CONFIGURABLE)
 * Allows separating tables per client or environment (e.g. test_leads)
 * ============================================================================
 */
function getTableName($type = 'leads') {
    loadEnv();
    switch ($type) {
        case 'interactions':
        case 'assessment_interactions':
            $t = getenv('DB_TABLE_INTERACTIONS') ?: 'test_assessment_interactions';
            break;
        case 'leads':
        default:
            $t = getenv('DB_TABLE_LEADS') ?: 'test_leads';
            break;
    }
    // Sanitize identifier for SQL safety
    return preg_replace('/[^a-zA-Z0-9_]/', '', $t);
}

if (!defined('TABLE_LEADS')) {
    define('TABLE_LEADS', getTableName('leads'));
}
if (!defined('TABLE_INTERACTIONS')) {
    define('TABLE_INTERACTIONS', getTableName('interactions'));
}
