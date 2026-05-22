<?php

declare(strict_types=1);

if ($argc !== 2 || trim($argv[1]) === '') {
    fwrite(STDERR, "Usage: php tools/stamp-lane-commit.php <commit>\n");
    exit(1);
}

$root = dirname(__DIR__);
$commit = trim($argv[1]);
$statusFiles = glob($root . '/lanes/*/lane-status.json') ?: [];
sort($statusFiles);

foreach ($statusFiles as $path) {
    $status = json_decode((string) file_get_contents($path), true);
    if (!is_array($status)) {
        throw new RuntimeException("Invalid status JSON: {$path}");
    }
    $status['latestCommit'] = $commit;
    file_put_contents($path, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

fwrite(STDOUT, 'Stamped ' . count($statusFiles) . " lane status files with {$commit}\n");

