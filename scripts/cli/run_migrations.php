<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/env.php';
require_once __DIR__ . '/../../lib/db.php';

env_load();

$pdo = pdo_connect_from_env();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Connessione al database non disponibile. Verifica le variabili d'ambiente.\n");
    exit(1);
}

$pdo->exec("SET NAMES utf8mb4");

$basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'sql';
$migrationsFile = $basePath . DIRECTORY_SEPARATOR . 'migrations.sql';
$seedFile = $basePath . DIRECTORY_SEPARATOR . 'seed.sql';

runSqlFile($pdo, $migrationsFile, 'Migrazioni');
runSqlFile($pdo, $seedFile, 'Seed');

fwrite(STDOUT, "Migrazioni completate con successo.\n");

function runSqlFile(PDO $pdo, string $path, string $label): void
{
    if (!is_readable($path)) {
        fwrite(STDERR, sprintf("File %s non trovato: %s\n", $label, $path));
        exit(1);
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, sprintf("Impossibile leggere %s (%s).\n", $label, $path));
        exit(1);
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $contents)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (Throwable $exception) {
            fwrite(STDERR, sprintf("Errore durante %s: %s\nSQL: %s\n", $label, $exception->getMessage(), $statement));
            exit(1);
        }
    }

    fwrite(STDOUT, sprintf("%s eseguiti (%d query).\n", $label, count($statements)));
}
