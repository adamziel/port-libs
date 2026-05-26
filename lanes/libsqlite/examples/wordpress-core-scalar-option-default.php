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
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-core-scalar-option-default.php abs|round|sign|typeof|quote|coalesce|ifnull|nullif|min|max|lower|upper|length|substr|substring|trim|ltrim|rtrim|replace|instr|concat|concat_ws|printf|format|like|glob|likely|unlikely|likelihood|iif|if|hex|unhex|char|unicode|octet_length|zeroblob|random|randomblob|date|time|datetime|julianday|unixepoch|strftime|timediff arg...\n");
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
    'nativeUtf8TextUnits' => [
        'length' => SQLiteCoreScalarFunction::sqlFunctionArguments('length', ['💡éx中']),
        'substring' => SQLiteCoreScalarFunction::sqlFunctionArguments('substr', ['💡éx中', 2, 2]),
        'instr' => SQLiteCoreScalarFunction::sqlFunctionArguments('instr', ['💡éx中', '中']),
    ],
    'wordpressUse' => 'Preview core SQLite scalar defaulting, quoting, typing, numeric coercion, sign checks, min/max selection, ASCII case folding, UTF-8 character length checks, substr/substring slicing, trim/replace cleanup, instr matching, concat/concat_ws option-key assembly, printf/format status rendering, like/glob option-name predicate dispatch, likely/unlikely/likelihood planner-hint pass-through, iif/if conditional fallback selection, hex/unhex/char/unicode/octet_length, zeroblob, random/randomblob, and bounded UTC date/time/timediff diagnostics for copied wp_options values before local import or repair without a hard mbstring dependency.',
    'formattedOptionPreview' => SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['option=%Q autoload=%s rowid=%04d', 'plugin_cache', 'yes', 7]),
    'predicatePreview' => [
        'like' => SQLiteCoreScalarFunction::sqlFunctionArguments('like', ['plugin\_%', 'plugin_cache', '\\']),
        'glob' => SQLiteCoreScalarFunction::sqlFunctionArguments('glob', ['plugin_[a-z]*', 'plugin_cache']),
    ],
    'plannerHintPreview' => SQLiteCoreScalarFunction::sqlFunctionArguments('likelihood', ['autoload = yes', 0.9375]),
    'conditionalDefaultPreview' => SQLiteCoreScalarFunction::sqlFunctionArguments('iif', [0, 'network-value', 1, 'site-default', 'fallback']),
    'timestampPreview' => [
        'datetime' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2026-05-26 16:12:34', '+1 day', 'start of day']),
        'monthBucket' => SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2026-05-26 16:12:34', 'start of month']),
        'nextWeeklyCron' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2026-05-26 16:12:34', 'weekday 0', 'start of day']),
        'unixepoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['2026-05-26 16:12:34']),
        'strftime' => SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%dT%H:%M:%SZ', 1779811954, 'unixepoch']),
        'timediff' => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27 18:42:34', '2026-05-26 16:12:34']),
    ],
    'noncePreviewBytes' => strlen(SQLiteCoreScalarFunction::sqlFunctionArguments('randomblob', [12])->bytes),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
