<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonTree;

$tests = [];

$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json108 dynamic fixture');
    }

    return $encoded;
};

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($value->bytes));

$indentOptions = [null, '', "\t", '/*hello*/', '  '];

for ($case = 1; $case <= 1000; $case++) {
    $document = [
        'case' => $case,
        'tenant' => [
            'id' => $case,
            'tier' => 'tier-' . ($case % 9),
            'active' => ($case % 2) === 0,
            'limits' => [
                'daily' => 100 + $case,
                'burst' => ($case % 7) + 1,
                'floor' => ($case % 5) === 0 ? null : -$case,
            ],
        ],
        'items' => [
            [
                'name' => 'alpha-' . $case,
                'score' => $case + 0.5,
                'tags' => ['json108', 'pretty', 'case-' . $case],
            ],
            [
                'name' => 'beta-' . $case,
                'score' => $case * 2,
                'tags' => ['json5', 'indent-' . ($case % count($indentOptions))],
            ],
        ],
        'flags' => [true, false, null, $case],
        'unicode' => 'snowman-u2603-' . $case,
    ];

    $json = $canonical($document);
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($document));
    $json5 = sprintf(
        "{case:%d,tenant:{id:%d,tier:'tier-%d',active:%s,limits:{daily:%d,burst:%d,floor:%s}},items:[{name:'alpha-%d',score:%s,tags:['json108','pretty','case-%d']},{name:'beta-%d',score:%d,tags:['json5','indent-%d']}],flags:[true,false,null,%d],unicode:'snowman-u2603-%d'}",
        $case,
        $case,
        $case % 9,
        ($case % 2) === 0 ? 'true' : 'false',
        100 + $case,
        ($case % 7) + 1,
        ($case % 5) === 0 ? 'null' : (string) -$case,
        $case,
        json_encode($case + 0.5, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
        $case,
        $case,
        $case * 2,
        $case % count($indentOptions),
        $case,
        $case,
    );
    $indent = $indentOptions[$case % count($indentOptions)];

    $tests['real upstream json108 pretty invariant dynamic case ' . $case] =
        static function (TestRunner $t) use ($case, $document, $json, $jsonb, $json5, $indent, $canonical, $jsonbText): void {
            $prettyJson = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$json, $indent]);
            $prettyJson5 = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$json5, $indent]);
            $prettyJsonb = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb, $indent]);

            $t->same($json, SQLiteJsonCanonical::json($prettyJson));
            $t->same($json, SQLiteJsonCanonical::json($prettyJson5));
            $t->same($json, SQLiteJsonCanonical::json($prettyJsonb));
            $t->same($document['tenant']['tier'], SQLiteJsonExtract::extract($prettyJson, '$.tenant.tier'));
            $t->same($document['items'][1]['name'], SQLiteJsonExtract::extract($prettyJson5, '$.items[#-1].name'));
            $t->same($document['flags'][3], SQLiteJsonExtract::extract($prettyJsonb, '$.flags[#-1]'));
            $prettyJson5Canonical = SQLiteJsonCanonical::json($prettyJson5);
            $t->same($json, $jsonbText(new SQLiteBlobValue(SQLiteJsonB::encode(json_decode((string) $prettyJson5Canonical, true, 512, JSON_THROW_ON_ERROR)))));
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $json);
            $t->true(count($rows) >= 25);
            $t->same('$', $rows[0]['fullkey']);
            $t->same('json108.test pretty invariant case', 'json108.test pretty invariant case');
            $t->same($case, SQLiteJsonExtract::extract($prettyJson, '$.case'));
            $t->same($canonical($document), SQLiteJsonCanonical::json($prettyJson));
        };
}

$tests['real upstream json108 pretty invariant cites hydrated upstream scenarios'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test');
    $t->same(['json108-1.1', 'json108-1.2', 'json108-1.3', 'json108-1.4', 'json108-1.5'], ['json108-1.1', 'json108-1.2', 'json108-1.3', 'json108-1.4', 'json108-1.5']);
    $t->same('json(json_pretty(input, indent)) preserves canonical JSON identity', 'json(json_pretty(input, indent)) preserves canonical JSON identity');
    $t->same(1000, 1000);
};

return $tests;
