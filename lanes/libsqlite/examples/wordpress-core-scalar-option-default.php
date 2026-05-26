<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$functionName = $argv[1] ?? null;
$arguments = array_slice($argv, 2);
if ($functionName === '--self-test') {
    $functionName = 'coalesce';
    $arguments = ['null', 'published'];
} elseif ($functionName === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-core-scalar-option-default.php abs|round|typeof|quote|coalesce|ifnull|nullif arg...\n");
    exit(1);
}

$coerceArgument = static function (string $value): mixed {
    if (str_starts_with($value, 'blob:')) {
        $bytes = hex2bin(substr($value, 5));
        if ($bytes === false) {
            throw new InvalidArgumentException('BLOB arguments must use blob:<hex>');
        }

        return new SQLiteBlobValue($bytes);
    }
    if (strcasecmp($value, 'null') === 0) {
        return null;
    }
    if (preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }

    return $value;
};

$typedArguments = array_map($coerceArgument, $arguments);
$result = SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $typedArguments);

echo json_encode([
    'functionName' => $functionName,
    'arguments' => array_map(static fn (mixed $value): mixed => $value instanceof SQLiteBlobValue ? 'blob:' . bin2hex($value->bytes) : $value, $typedArguments),
    'result' => $result instanceof SQLiteBlobValue ? 'blob:' . bin2hex($result->bytes) : $result,
    'wordpressUse' => 'Preview core SQLite scalar defaulting, quoting, typing, and numeric coercion for copied wp_options values before local import or repair.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
