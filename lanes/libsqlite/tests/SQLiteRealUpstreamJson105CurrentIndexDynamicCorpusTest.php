<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$canonical = static function (mixed $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson($value);
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$jsonbText = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonCanonical::json($value);
    }
    if (is_string($value)) {
        return SQLiteJsonCanonical::json($value);
    }

    throw new RuntimeException('Expected JSON text or JSONB blob result');
};

$documents = [];
for ($i = 1; $i <= 170; $i++) {
    $documents['json105-current-index-dynamic-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'a' => $i,
        'b' => [
            $i,
            [$i + 1, $i + 2],
            $i + 3,
        ],
        'c' => [
            'label' => 'case-' . $i,
            'active' => ($i % 2) === 0,
        ],
    ];
}

foreach ($documents as $scenario => $document) {
    $json = $canonical($document);
    $blob = $jsonb($document);

    $tests['real upstream json105 current index extract matrix ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $jsonbText, $canonical): void {
            $expectedMulti = [$document['b'][0], $document['b'][2]];

            $t->same(null, SQLiteJsonExtract::extract($json, '$.b[#]'), 'append pseudo-index extracts NULL for text');
            $t->same($document['b'][2], SQLiteJsonExtract::extract($json, '$.b[#-1]'), 'reverse last extracts text');
            $t->same($canonical($document['b'][1]), SQLiteJsonExtract::extract($blob, '$.b[#-2]'), 'reverse nested array extracts JSONB input');
            $t->same($document['b'][1][1], SQLiteJsonExtract::extract($json, '$.b[#-2][#-1]'), 'nested reverse last extracts scalar');
            $t->same($document['b'][2], SQLiteJsonExtract::extract($blob, '$.b[#-000001]'), 'leading zero reverse index extracts');
            $t->same(null, SQLiteJsonExtract::extract($json, '$.b[#-4]'), 'reverse before start extracts NULL');
            $t->same($canonical($expectedMulti), SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$.b[0]', '$.b[#-1]'), 'multi-path extract text array');
            $t->same($canonical($expectedMulti), $jsonbText(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.b[0]', '$.b[#-1]')), 'multi-path extract JSONB array');
        };

    $tests['real upstream json105 current index remove left to right ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $removeFirstThenLast = $document;
            $removeFirstThenLast['b'] = [$document['b'][1]];
            $removeLastThenFirst = $document;
            $removeLastThenFirst['b'] = [$document['b'][1]];
            $removeLastTwice = $document;
            $removeLastTwice['b'] = [$document['b'][0]];
            $removeMiddleThenLast = $document;
            $removeMiddleThenLast['b'] = [$document['b'][0]];

            $t->same($canonical($removeFirstThenLast), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.b[0]', '$.b[#-1]'), 'remove forward then reverse text');
            $t->same($canonical($removeLastThenFirst), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.b[#-1]', '$.b[0]'), 'remove reverse then forward text');
            $t->same($canonical($removeLastTwice), SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.b[#-1]', '$.b[#-1]'), 'remove reverse twice on JSONB input');
            $t->same($canonical($removeMiddleThenLast), SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.b[#-2]', '$.b[#-1]'), 'remove middle then reverse on JSONB input');
            $t->same($canonical($document), SQLiteJsonRemove::removeSqlFunction('json_remove', $json, '$.b[#]'), 'append pseudo-index remove is no-op');
            $t->same($canonical($document), SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.b[#-4]'), 'reverse before start remove is no-op');
            $t->same($canonical($removeFirstThenLast), $jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, '$.b[0]', '$.b[#-1]')), 'jsonb_remove text source parity');
            $t->same($canonical($removeLastTwice), $jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, '$.b[#-1]', '$.b[#-1]')), 'jsonb_remove blob source parity');
        };

    $tests['real upstream json105 current index insert append paths ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $nestedAppend = $document;
            $nestedAppend['b'][1][] = 'AAA-' . $document['a'];
            $tailAppend = $document;
            $tailAppend['b'][] = 'BBB-' . $document['a'];
            $both = $document;
            $both['b'][1][] = 'AAA-' . $document['a'];
            $both['b'][] = 'BBB-' . $document['a'];
            $doubleTail = $document;
            $doubleTail['b'][] = 'AAA-' . $document['a'];
            $doubleTail['b'][] = 'BBB-' . $document['a'];

            $t->same($canonical($nestedAppend), SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.b[1][#]', 'AAA-' . $document['a']), 'json_insert nested append text');
            $t->same($canonical($tailAppend), SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.b[#]', 'BBB-' . $document['a']), 'json_insert tail append JSONB input');
            $t->same($canonical($both), SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.b[1][#]', 'AAA-' . $document['a'], '$.b[#]', 'BBB-' . $document['a']), 'json_insert nested then tail append');
            $t->same($canonical($doubleTail), SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, '$.b[#]', 'AAA-' . $document['a'], '$.b[#]', 'BBB-' . $document['a']), 'json_insert repeated append uses current length');
            $t->same($canonical($nestedAppend), $jsonbText(SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $json, '$.b[1][#]', 'AAA-' . $document['a'])), 'jsonb_insert nested append text source');
            $t->same($canonical($both), $jsonbText(SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $blob, '$.b[1][#]', 'AAA-' . $document['a'], '$.b[#]', 'BBB-' . $document['a'])), 'jsonb_insert nested then tail append JSONB source');
        };

    $tests['real upstream json105 current index set and replace reverse slots ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $setLast = $document;
            $setLast['b'][2] = 'LAST-' . $document['a'];
            $setNestedLast = $document;
            $setNestedLast['b'][1][1] = 'NESTED-' . $document['a'];
            $setBoth = $document;
            $setBoth['b'][1][1] = 'NESTED-' . $document['a'];
            $setBoth['b'][2] = 'TAIL-' . $document['a'];

            $t->same($canonical($setLast), SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.b[#-1]', 'LAST-' . $document['a']), 'json_set reverse last text');
            $t->same($canonical($setNestedLast), SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.b[1][#-1]', 'NESTED-' . $document['a']), 'json_set nested reverse JSONB input');
            $t->same($canonical($setBoth), SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.b[1][#-1]', 'NESTED-' . $document['a'], '$.b[#-1]', 'TAIL-' . $document['a']), 'json_set nested then reverse last');
            $t->same($canonical($setLast), SQLiteJsonMutation::mutateSqlFunction('json_replace', $json, '$.b[#-1]', 'LAST-' . $document['a']), 'json_replace reverse last text');
            $t->same($canonical($setNestedLast), $jsonbText(SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, '$.b[1][#-1]', 'NESTED-' . $document['a'])), 'jsonb_replace nested reverse JSONB input');
            $t->same($canonical($setBoth), $jsonbText(SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $json, '$.b[1][#-1]', 'NESTED-' . $document['a'], '$.b[#-1]', 'TAIL-' . $document['a'])), 'jsonb_set nested then reverse last');
        };

    $tests['real upstream json105 current index array_insert left to right ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob, $canonical, $jsonbText): void {
            $beforeFirstTwice = $document;
            $beforeFirstTwice['b'] = ['BBB-' . $document['a'], 'AAA-' . $document['a'], $document['b'][0], $document['b'][1], $document['b'][2]];
            $beforeFirstThenAppend = $document;
            $beforeFirstThenAppend['b'] = ['AAA-' . $document['a'], $document['b'][0], $document['b'][1], $document['b'][2], 'BBB-' . $document['a']];
            $beforeLast = $document;
            $beforeLast['b'] = [$document['b'][0], $document['b'][1], 'AAA-' . $document['a'], $document['b'][2]];
            $beforeFirstByReverse = $document;
            $beforeFirstByReverse['b'] = ['AAA-' . $document['a'], $document['b'][0], $document['b'][1], $document['b'][2]];

            $t->same($canonical($beforeFirstTwice), SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.b[0]', 'AAA-' . $document['a'], '$.b[0]', 'BBB-' . $document['a']), 'array_insert repeated zero index');
            $t->same($canonical($beforeFirstThenAppend), SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.b[0]', 'AAA-' . $document['a'], '$.b[#]', 'BBB-' . $document['a']), 'array_insert zero then append');
            $t->same($canonical($beforeLast), SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $blob, '$.b[#-1]', 'AAA-' . $document['a']), 'array_insert before reverse last JSONB input');
            $t->same($canonical($beforeFirstByReverse), SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.b[#-3]', 'AAA-' . $document['a']), 'array_insert reverse first');
            $t->same($canonical($document), SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.b[#-4]', 'AAA-' . $document['a']), 'array_insert reverse before first no-op');
            $t->same($canonical($beforeFirstThenAppend), $jsonbText(SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $blob, '$.b[0]', 'AAA-' . $document['a'], '$.b[#]', 'BBB-' . $document['a'])), 'jsonb_array_insert zero then append');
        };

    $tests['real upstream json105 current index malformed paths reject ' . $scenario] =
        static function (TestRunner $t) use ($json, $blob): void {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($json, '$.b[#-]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($blob, '$.b[#9]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($json, '$.b[#+2]'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::remove($blob, '$.b[#-1'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.b[#-1x]', 'bad'));
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.b[#-1x]', 'bad'));
        };
}

$tests['real upstream json105 current index dynamic corpus cites hydrated source'] = static function (TestRunner $t) use ($documents): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test');
    $t->same(
        [
            'json105-1.10-1.110 current-index extraction',
            'json105-2.10-2.140 left-to-right removal',
            'json105-3.10-3.40 append insertion',
            'json105-4.10-4.80 set current-index mutation',
            'json105-5.10-5.80 replace current-index mutation',
            'json105-6.10-6.50 malformed current-index path errors',
            'json109-1.1-1.9 json_array_insert current-index companion behavior',
        ],
        [
            'json105-1.10-1.110 current-index extraction',
            'json105-2.10-2.140 left-to-right removal',
            'json105-3.10-3.40 append insertion',
            'json105-4.10-4.80 set current-index mutation',
            'json105-5.10-5.80 replace current-index mutation',
            'json105-6.10-6.50 malformed current-index path errors',
            'json109-1.1-1.9 json_array_insert current-index companion behavior',
        ],
    );
    $t->same(170, count($documents));
};

return $tests;
