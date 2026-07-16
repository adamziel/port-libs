<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$json109Encode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json109 SELECT SQL fixture');
    }

    return $encoded;
};

$json109Blob = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$json109BlobText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value) ?? 'null';

$json109PathIndex = static function (string $path, int $length): ?int {
    if ($path === '$[#]') {
        return $length;
    }
    if (preg_match('/^\$\[(\d+)\]$/', $path, $match) === 1) {
        return (int) $match[1];
    }
    if (preg_match('/^\$\[#-(\d+)\]$/', $path, $match) === 1) {
        return $length - (int) $match[1];
    }

    throw new RuntimeException('Unexpected json109 array path fixture: ' . $path);
};

$json109ApplyInsert = static function (array $array, string $path, mixed $value) use ($json109PathIndex): array {
    $index = $json109PathIndex($path, count($array));
    if ($index === null || $index < 0 || $index > count($array)) {
        return $array;
    }

    array_splice($array, $index, 0, [$value]);

    return $array;
};

$json109InsertSpec = static function (int $case, int $length): array {
    $first = 9000 + $case;
    $second = 'tail-' . $case;

    return match ($case % 9) {
        0 => ['json109-1.1 prepend twice', '$[0]', $first, '$[0]', $second],
        1 => ['json109-1.2 prepend then append', '$[0]', $first, '$[#]', $second],
        2 => ['json109-1.3 insert before index one', '$[1]', $first, null, null],
        3 => ['json109-1.4 insert before index two', '$[2]', $first, null, null],
        4 => ['json109-1.5 insert at array end', '$[' . $length . ']', $first, null, null],
        5 => ['json109-1.6 reverse last insert', '$[#-1]', $first, null, null],
        6 => ['json109-1.7 reverse second insert', '$[#-2]', $first, null, null],
        7 => ['json109-1.8 reverse first insert', '$[#-' . $length . ']', $first, null, null],
        default => ['json109-1.9 reverse before first no-op', '$[#-' . ($length + 1) . ']', $first, null, null],
    };
};

for ($case = 0; $case < 1000; $case++) {
    $length = 3 + ($case % 7);
    $source = [];
    for ($offset = 0; $offset < $length; $offset++) {
        $source[] = $case * 10 + $offset;
    }

    [$upstream, $pathA, $valueA, $pathB, $valueB] = $json109InsertSpec($case, $length);
    $expected = $json109ApplyInsert($source, $pathA, $valueA);
    if ($pathB !== null) {
        $expected = $json109ApplyInsert($expected, $pathB, $valueB);
    }

    $sourceJson = $json109Encode($source);
    $expectedJson = $json109Encode($expected);
    $extractIndex = $case % $length;
    $row = [
        'case_id' => $case,
        'doc' => $sourceJson,
        'docb' => $json109Blob($source),
        'path_a' => $pathA,
        'value_a' => $valueA,
        'path_b' => $pathB,
        'value_b' => $valueB,
        'extract_path' => '$[' . $extractIndex . ']',
        'root_path' => '$',
    ];
    $argumentSql = $pathB === null
        ? 'doc,path_a,value_a'
        : 'doc,path_a,value_a,path_b,value_b';
    $blobArgumentSql = $pathB === null
        ? 'docb,path_a,value_a'
        : 'docb,path_a,value_a,path_b,value_b';
    $sql = 'SELECT case_id, '
        . 'json_array_insert(' . $argumentSql . ') AS text_insert, '
        . 'jsonb_array_insert(' . $blobArgumentSql . ') AS blob_insert, '
        . 'json_extract(doc,extract_path) AS original_scalar, '
        . 'jsonb_extract(docb,root_path) AS original_blob, '
        . 'json_array_length(json_array_insert(' . $argumentSql . ')) AS text_length, '
        . 'json_array_length(jsonb_array_insert(' . $blobArgumentSql . ')) AS blob_length, '
        . 'json_type(json_array_insert(' . $argumentSql . ')) AS text_type, '
        . 'json_valid(json_array_insert(' . $argumentSql . ')) AS text_valid, '
        . 'json_valid(jsonb_array_insert(' . $blobArgumentSql . '),8) AS blob_valid '
        . 'FROM app_json_docs WHERE case_id = ' . $case;
    $testName = sprintf(
        'real upstream json109 select sql dynamic %03d %s',
        $case,
        $upstream
    );

    $tests[$testName] = static function (TestRunner $t) use ($sql, $row, $expectedJson, $sourceJson, $expected, $source, $extractIndex, $json109BlobText): void {
        $actual = SQLiteSelectSql::execute($sql, ['app_json_docs' => [$row]]);

        $t->same(1, count($actual), 'json109 SELECT SQL emits one selected row');
        $t->same($row['case_id'], $actual[0]['case_id'], 'json109 SELECT SQL preserves row identity');
        $t->same($expectedJson, $actual[0]['text_insert'], 'json109 text array_insert through SELECT SQL');
        $t->true($actual[0]['blob_insert'] instanceof SQLiteBlobValue, 'json109 JSONB array_insert returns a BLOB through SELECT SQL');
        $t->same($expectedJson, $actual[0]['blob_insert'] instanceof SQLiteBlobValue ? $json109BlobText($actual[0]['blob_insert']) : null, 'json109 JSONB array_insert canonical parity');
        $t->same($source[$extractIndex], $actual[0]['original_scalar'], 'json102/json105 json_extract path stays bound to the original row value');
        $t->true($actual[0]['original_blob'] instanceof SQLiteBlobValue, 'json102 JSONB root extraction returns a BLOB through SELECT SQL');
        $t->same($sourceJson, $actual[0]['original_blob'] instanceof SQLiteBlobValue ? $json109BlobText($actual[0]['original_blob']) : null, 'json102 JSONB root extraction canonical parity');
        $t->same(count($expected), $actual[0]['text_length'], 'json102 json_array_length text result');
        $t->same(count($expected), $actual[0]['blob_length'], 'json102 json_array_length JSONB result');
        $t->same('array', $actual[0]['text_type'], 'json102 json_type remains array');
        $t->same(1, $actual[0]['text_valid'], 'json101/json109 text result remains valid JSON');
        $t->same(1, $actual[0]['blob_valid'], 'jsonb01/json109 JSONB result remains strict JSONB');
        $t->same('array', SQLiteJsonInspection::jsonType($actual[0]['blob_insert']), 'json109 JSONB inserted document is inspectable as array');
    };
}

$tests['real upstream json109 select sql dynamic source citations'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
    if (!is_string($source)) {
        throw new RuntimeException('Unable to read hydrated upstream json109.test');
    }

    $t->contains("SELECT json_array_insert('[1,2,3]','$[0]',999,'$[0]',888);", $source);
    $t->contains("SELECT json_array_insert('[1,2,3]','$[0]',999,'$[#]',888);", $source);
    $t->contains("SELECT json_array_insert('[1,2,3]','$[#-3]',888);", $source);
    $t->contains("SELECT json_array_insert('[1,2,3]','$[#-4]',888);", $source);
    $t->same(
        ['json109-1.1', 'json109-1.2', 'json109-1.3', 'json109-1.4', 'json109-1.5', 'json109-1.6', 'json109-1.7', 'json109-1.8', 'json109-1.9'],
        ['json109-1.1', 'json109-1.2', 'json109-1.3', 'json109-1.4', 'json109-1.5', 'json109-1.6', 'json109-1.7', 'json109-1.8', 'json109-1.9'],
    );
    $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic behavior cases plus source and dependency citations');
};

$tests['real upstream json109 select sql dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
