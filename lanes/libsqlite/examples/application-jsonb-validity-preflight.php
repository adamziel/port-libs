<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validSettings = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'migrations' => ['core', 'cache'],
    ],
]);
$superficialOnly = "\x8b\xff" . str_repeat("\0", 7);
$castTextJsonBlob = '{"a":35}';
$badScalarPayload = "\x10\0";

$decodeStatus = static function (string $bytes): string {
    try {
        SQLiteJsonB::decode($bytes);

        return 'decoded';
    } catch (InvalidArgumentException) {
        return 'decode-error';
    }
};

$checks = [];
foreach ([
    'valid_wp_options_settings' => $validSettings,
    'copied_large_corrupt_jsonb_blob' => $superficialOnly,
    'cast_text_json_blob' => $castTextJsonBlob,
    'bad_scalar_payload' => $badScalarPayload,
] as $name => $bytes) {
    $checks[] = [
        'name' => $name,
        'hex' => bin2hex($bytes),
        'bytes' => strlen($bytes),
        'jsonValidFlag4Superficial' => SQLiteJsonB::isSuperficiallyJsonB($bytes),
        'jsonValidFlag8Strict' => SQLiteJsonB::isStrictlyWellFormed($bytes),
        'decodeStatus' => $decodeStatus($bytes),
    ];
}

echo json_encode([
    'checks' => $checks,
    'applicationUse' => 'Fast preflight SQLite JSONB wp_options option_value BLOBs copied from another store; superficial-only blobs should be scheduled for strict decode or repair before plugin settings are trusted.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
