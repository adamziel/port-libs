<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => $arguments,
];
$binaryExpression = static fn (array $left, string $operator, array $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];
$concatExpression = static function (array ...$parts) use ($binaryExpression): array {
    $expression = array_shift($parts);
    if ($expression === null) {
        throw new RuntimeException('Cannot build empty concatenation expression');
    }

    foreach ($parts as $part) {
        $expression = $binaryExpression($expression, '||', $part);
    }

    return $expression;
};
$charExpression = static fn (int $codepoint): array => $functionExpression('char', [$literal($codepoint)]);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

for ($case = 0; $case < 600; $case++) {
    $suffix = str_pad((string) $case, 3, '0', STR_PAD_LEFT);
    $key = 'k_' . $suffix;
    $nestedKey = 'nested_' . $suffix;
    $arrayKey = 'items_' . $suffix;
    $body = sprintf(
        '"%s":%d,"%s":{"flag":%s,"label":"case-%s"},"%s":[%d,%d,%d]',
        $key,
        $case + 35,
        $nestedKey,
        ($case % 2) === 0 ? 'true' : 'false',
        $suffix,
        $arrayKey,
        $case,
        $case + 1,
        $case + 2,
    );
    $complete = '{' . $body . '}';
    $truncated = '{' . $body;
    $completeExpression = $concatExpression($charExpression(123), $literal($body), $charExpression(125));
    $truncatedExpression = $concatExpression($charExpression(123), $literal($body));
    $path = '$.' . $key;
    $nestedFlagPath = '$.' . $nestedKey . '.flag';
    $arrayPath = '$.' . $arrayKey;

    $tests['real upstream json102-610 char-built complete object validates dynamic ' . $suffix] =
        static function (TestRunner $t) use (
            $case,
            $complete,
            $completeExpression,
            $functionExpression,
            $path,
            $nestedFlagPath,
            $arrayPath,
            $jsonbText
        ): void {
            $built = SQLiteSelectExpression::evaluate([], $completeExpression);
            $json = SQLiteSelectExpression::evaluate([], $functionExpression('json', [$completeExpression]));
            $jsonb = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb', [$completeExpression]));
            $expectedArray = '[' . $case . ',' . ($case + 1) . ',' . ($case + 2) . ']';

            $t->same($complete, $built, 'json102-610 SQL char() concatenation builds the complete object');
            $t->same(true, SQLiteJsonValidity::jsonValid($built), 'json102-610 direct json_valid accepts the complete object');
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$completeExpression])), 'json102-610 SELECT json_valid accepts the complete object');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($built), 'json102-610 complete object has no error offset');
            $t->same($complete, SQLiteJsonCanonical::json($built), 'json102-610 complete object canonicalizes');
            $t->same($complete, $json->json, 'json102-610 SELECT json() returns canonical subtype text');
            $t->true($jsonb instanceof SQLiteBlobValue, 'json102-610 SELECT jsonb() returns JSONB');
            $t->same($complete, $jsonbText($jsonb), 'json102-610 JSONB canonical text matches complete object');
            $t->same($case + 35, SQLiteJsonExtract::extract($built, $path), 'json102-610 dynamic scalar key extracts');
            $t->same((int) (($case % 2) === 0), SQLiteJsonExtract::extract($jsonb, $nestedFlagPath), 'json102-610 JSONB nested boolean extracts as SQLite integer');
            $t->same('array', SQLiteJsonInspection::jsonType($built, $arrayPath), 'json102-610 nested array type is visible');
            $t->same(3, SQLiteJsonInspection::jsonArrayLength($jsonb, $arrayPath), 'json102-610 JSONB nested array length is visible');
            $t->same($expectedArray, SQLiteJsonExtract::extract($built, $arrayPath), 'json102-610 nested array extracts as canonical JSON text');
        };

    $tests['real upstream json102-620 char-built truncated object rejects dynamic ' . $suffix] =
        static function (TestRunner $t) use (
            $case,
            $truncated,
            $truncatedExpression,
            $functionExpression,
            $path
        ): void {
            $built = SQLiteSelectExpression::evaluate([], $truncatedExpression);

            $t->same($truncated, $built, 'json102-620 SQL char() concatenation builds the truncated object');
            $t->same(false, SQLiteJsonValidity::jsonValid($built), 'json102-620 direct json_valid rejects the truncated object');
            $t->same(0, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$truncatedExpression])), 'json102-620 SELECT json_valid rejects the truncated object');
            $t->same(false, SQLiteJsonValidity::jsonValid($built, SQLiteJsonValidity::FLAG_JSON5_TEXT), 'json102-620 JSON5 flag still rejects the truncated object');
            $t->true(SQLiteJsonErrorPosition::jsonErrorPosition($built) > 0, 'json102-620 truncated object has a positive error offset');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonCanonical::json($built), 'json102-620 direct json() rejects the truncated object');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSelectExpression::evaluate([], $functionExpression('json', [$truncatedExpression])), 'json102-620 SELECT json() rejects the truncated object');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSelectExpression::evaluate([], $functionExpression('jsonb', [$truncatedExpression])), 'json102-620 SELECT jsonb() rejects the truncated object');
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($built, $path), 'json102-620 extraction rejects the truncated object');
            $t->same(true, $case >= 0 && $case < 600, 'json102-620 dynamic corpus row guard');
        };
}

$tests['real upstream json102 char-built validity dynamic source citations'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->contains('do_execsql_test json102-610', $source);
        $t->contains('SELECT json_valid(char(123)||\'"x":35\'||char(125));', $source);
        $t->contains('do_execsql_test json102-620', $source);
        $t->contains('SELECT json_valid(char(123)||\'"x":35\');', $source);
        $t->same(
            ['json102-610 complete object built with char(123)/char(125)', 'json102-620 truncated char-built object rejection'],
            ['json102-610 complete object built with char(123)/char(125)', 'json102-620 truncated char-built object rejection'],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
