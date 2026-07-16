<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$targetInput = $argv[1] ?? null;
$patchInput = $argv[2] ?? null;
if ($targetInput === null || $patchInput === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-patch-option-field.php json-or-hex json-or-hex-patch\n");
    fwrite(STDERR, "Applies SQLite jsonb_patch merge-patch semantics to a wp_options option_value fixture and prints the resulting JSONB bytes.\n");
    exit(1);
}

$decodeJsonInput = static function (string $text): mixed {
    try {
        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return SQLiteJson5Parser::decode($text);
    }
};

$decodeInput = static function (string $input) use ($decodeJsonInput): array {
    if (str_starts_with($input, 'hex:')) {
        $jsonb = hex2bin(substr($input, 4));
        if (!is_string($jsonb)) {
            throw new InvalidArgumentException('JSONB hex input is malformed');
        }

        return [SQLiteJsonB::decode($jsonb), $jsonb, 'sqlite-jsonb-hex'];
    }

    $decoded = $decodeJsonInput($input);

    return [$decoded, SQLiteJsonB::encode($decoded), 'json-or-json5'];
};

[$decodedTarget, $targetJsonb, $targetKind] = $decodeInput($targetInput);
[$decodedPatch, $patchJsonb, $patchKind] = $decodeInput($patchInput);

$patched = SQLiteJsonB::patch($targetJsonb, $patchJsonb);

echo json_encode([
    'targetKind' => $targetKind,
    'patchKind' => $patchKind,
    'decodedBefore' => $decodedTarget,
    'decodedPatch' => $decodedPatch,
    'decodedAfter' => SQLiteJsonB::decode($patched),
    'sqliteJsonbHex' => bin2hex($patched),
    'applicationUse' => 'Preflight or fixture-update JSONB wp_options blobs by applying SQLite RFC-7396 merge patches where null object members delete keys and arrays replace whole arrays.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
