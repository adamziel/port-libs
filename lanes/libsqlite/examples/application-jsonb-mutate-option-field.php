<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteBlobValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$input = $argv[1] ?? null;
$operation = $argv[2] ?? null;
$arguments = array_slice($argv, 3);

if ($input === null || !in_array($operation, ['insert', 'set', 'replace', 'json_insert', 'jsonb_insert', 'json_set', 'jsonb_set', 'json_replace', 'jsonb_replace'], true) || count($arguments) < 2 || count($arguments) % 2 !== 0) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-mutate-option-field.php json-or-hex insert|set|replace|json_set|jsonb_set|json_insert|jsonb_insert|json_replace|jsonb_replace path value [path value...]\n");
    fwrite(STDERR, "Mutates JSON paths in a SQLite JSONB wp_options option_value fixture and prints SQLite text or JSONB SQL-dispatch results.\n");
    exit(1);
}

$decodeJsonInput = static function (string $text): mixed {
    try {
        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return SQLiteJson5Parser::decode($text);
    }
};

$decodeValue = static function (string $text) use ($decodeJsonInput): mixed {
    if (str_starts_with($text, 'text:')) {
        return substr($text, 5);
    }
    if (str_starts_with($text, 'hex:')) {
        $bytes = hex2bin(substr($text, 4));
        if (!is_string($bytes)) {
            throw new InvalidArgumentException('JSONB value hex is malformed');
        }

        return new SQLiteBlobValue($bytes);
    }
    if (str_starts_with($text, 'json:')) {
        return new SQLiteJsonSubtypeValue(substr($text, 5));
    }

    try {
        return $decodeJsonInput($text);
    } catch (InvalidArgumentException) {
        return $text;
    }
};

if (str_starts_with($input, 'hex:')) {
    $jsonb = hex2bin(substr($input, 4));
    if (!is_string($jsonb)) {
        throw new InvalidArgumentException('JSONB hex input is malformed');
    }
    $decoded = SQLiteJsonB::decode($jsonb);
    $inputKind = 'sqlite-jsonb-hex';
} else {
    $decoded = $decodeJsonInput($input);
    $jsonb = SQLiteJsonB::encode($decoded);
    $inputKind = 'json-or-json5';
}

$path = array_shift($arguments);
$value = $decodeValue(array_shift($arguments));
$extraPairs = [];
while ($arguments !== []) {
    $extraPath = array_shift($arguments);
    $extraValue = $decodeValue(array_shift($arguments));
    $extraPairs[] = $extraPath;
    $extraPairs[] = $extraValue;
}

$function = match ($operation) {
    'insert' => 'jsonb_insert',
    'set' => 'jsonb_set',
    'replace' => 'jsonb_replace',
    default => $operation,
};
$dispatchFunction = strtoupper($function);
$result = SQLiteJsonMutation::mutateSqlFunctionArguments($dispatchFunction, [
    new SQLiteBlobValue($jsonb),
    $path,
    $value,
    ...$extraPairs,
]);
$mutated = $result instanceof SQLiteBlobValue ? $result->bytes : SQLiteJsonB::encode($decodeJsonInput((string) $result));

echo json_encode([
    'inputKind' => $inputKind,
    'operation' => $operation,
    'sqlFunction' => $dispatchFunction,
    'dispatch' => 'argument-vector',
    'resultKind' => $result instanceof SQLiteBlobValue ? 'sqlite-jsonb' : 'text-json',
    'decodedBefore' => $decoded,
    'decodedAfter' => SQLiteJsonB::decode($mutated),
    'sqliteJsonbHex' => $result instanceof SQLiteBlobValue ? bin2hex($mutated) : null,
    'jsonText' => is_string($result) ? $result : null,
    'applicationUse' => 'Preflight or fixture-update wp_options JSON values by applying SQLite json_insert/json_set/json_replace or jsonb_* SQL-dispatch path edits without requiring the SQLite extension.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
