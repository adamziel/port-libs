<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPatch;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$function = $argv[1] ?? 'JSON_PATCH';
$targetInput = $argv[2] ?? '{"plugin":{"enabled":false,"legacyToken":"secret","rules":["seo","cache"],"nested":{"old":1,"keep":2}},"keep":1}';
$patchInput = $argv[3] ?? '{"plugin":{"enabled":true,"legacyToken":null,"rules":["cache"],"nested":{"old":null,"new":3}}}';

$decodeInput = static function (string $input): array {
    if (str_starts_with($input, 'hex:')) {
        $bytes = hex2bin(substr($input, 4));
        if (!is_string($bytes)) {
            throw new InvalidArgumentException('JSONB hex input is malformed');
        }

        return [new SQLiteBlobValue($bytes), SQLiteJsonB::isSuperficiallyJsonB($bytes) ? 'sqlite-jsonb-hex' : 'cast-text-blob-hex'];
    }

    return [$input, 'json-or-json5-text'];
};

[$target, $targetKind] = $decodeInput($targetInput);
[$patch, $patchKind] = $decodeInput($patchInput);

$result = SQLiteJsonPatch::patchSqlFunctionArguments($function, [$target, $patch]);

echo json_encode([
    'function' => $function,
    'argumentVector' => true,
    'targetKind' => $targetKind,
    'patchKind' => $patchKind,
    'resultType' => $result instanceof SQLiteBlobValue ? 'sqlite-jsonb-blob' : 'json-text-or-sql-null',
    'jsonAfter' => $result instanceof SQLiteBlobValue ? SQLiteJsonB::decode($result->bytes) : $result,
    'jsonbHexAfter' => $result instanceof SQLiteBlobValue ? bin2hex($result->bytes) : null,
    'applicationUse' => 'Local-only wp_options merge-patch preflight that preserves SQLite json_patch() text results versus jsonb_patch() JSONB blob results through uppercase SQL argument-vector dispatch before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
