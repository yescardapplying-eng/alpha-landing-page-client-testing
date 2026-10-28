<?php
/**
 * ============================================================================
 * DATABASE MIGRATION RUNNER
 * Creates necessary tables in TiDB Cloud / MySQL database
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    echo "Connecting to MySQL/TiDB database...\n";
    $pdo = getDBConnection();
    echo "Connected successfully!\n\n";

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);

    // Remove single-line comments (-- ...) and multi-line comments (/* ... */)
    $cleanSql = preg_replace('/--.*$/m', '', $sql);
    $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);

    // Split SQL by semicolons
    $statements = explode(';', $cleanSql);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            echo "Executing statement:\n" . substr($stmt, 0, 60) . "...\n";
            $pdo->exec($stmt);
        }
    }

    echo "\nAll tables created/verified successfully!\n";

    // Verify tables
    $leadsTable = TABLE_LEADS;
    $interactionsTable = TABLE_INTERACTIONS;

    $stmt = $pdo->query("SHOW TABLES LIKE '{$leadsTable}'");
    $leadsFound = $stmt->fetch();
    echo "Table '{$leadsTable}' exists: " . ($leadsFound ? "YES" : "NO") . "\n";

    $stmt = $pdo->query("SHOW TABLES LIKE '{$interactionsTable}'");
    $interactionsFound = $stmt->fetch();
    echo "Table '{$interactionsTable}' exists: " . ($interactionsFound ? "YES" : "NO") . "\n";

} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
