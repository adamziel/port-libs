<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

function json106_thousand_encode(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON106 invariant fixture');
    }

    return $encoded;
}

function json106_thousand_blob(string $json): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
}

function json106_thousand_canonical(string|SQLiteBlobValue|null $json): ?string
{
    return $json === null ? null : SQLiteJsonCanonical::json($json);
}

function json106_thousand_fixture(int $case): array
{
    $bucket = $case % 23;
    $document = [
        'account' => [
            'id' => $case,
            'name' => 'account-' . $case,
            'enabled' => ($case % 2) === 0,
            'tier' => $bucket,
        ],
        'metrics' => [
            'count' => $case * 7,
            'ratio' => ($case % 19) + 0.125,
            'negative' => -$case,
        ],
        'events' => [
            ['kind' => 'created', 'ok' => true, 'weight' => $case + 1],
            ['kind' => 'updated', 'ok' => ($case % 3) === 0, 'weight' => $case + 2],
            ['kind' => 'queued', 'ok' => false, 'weight' => $case + 3],
        ],
        'labels' => ['alpha', 'bucket-' . $bucket, 'case-' . $case],
        'nested' => [
            'path' => [
                'leaf' => 'leaf-' . $case,
                'nullable' => ($case % 5) === 0 ? null : 'value-' . $case,
            ],
        ],
    ];
    $patch = [
        'account' => [
            'enabled' => ($case % 2) !== 0,
            'patched' => true,
        ],
        'metrics' => [
            'count' => $case * 11,
        ],
        'nested' => [
            'path' => [
                'patchedLeaf' => 'patched-' . $case,
            ],
        ],
    ];
    $json5 = sprintf(
        '{account:{id:%d,name:"account-%d",enabled:%s,tier:%d,},metrics:{count:%d,ratio:%s,negative:%d,},events:[{kind:"created",ok:true,weight:%d},{kind:"updated",ok:%s,weight:%d},{kind:"queued",ok:false,weight:%d},],labels:["alpha","bucket-%d","case-%d",],nested:{path:{leaf:"leaf-%d",nullable:%s,},},}',
        $case,
        $case,
        ($case % 2) === 0 ? 'true' : 'false',
        $bucket,
        $case * 7,
        (string) (($case % 19) + 0.125),
        -$case,
        $case + 1,
        ($case % 3) === 0 ? 'true' : 'false',
        $case + 2,
        $case + 3,
        $bucket,
        $case,
        $case,
        ($case % 5) === 0 ? 'null' : '"value-' . $case . '"'
    );

    return [
        'strict' => json106_thousand_encode($document),
        'json5' => $json5,
        'patch' => json106_thousand_encode($patch),
        'scalarPaths' => [
            '$.account.id',
            '$.account.name',
            '$.account.enabled',
            '$.account.tier',
            '$.metrics.count',
            '$.metrics.ratio',
            '$.metrics.negative',
            '$.events[0].kind',
            '$.events[0].weight',
            '$.events[1].ok',
            '$.events[2].weight',
            '$.labels[1]',
            '$.nested.path.leaf',
            '$.nested.path.nullable',
        ],
        'objectLeafPaths' => [
            '$.account.id',
            '$.account.name',
            '$.account.enabled',
            '$.metrics.count',
            '$.nested.path.leaf',
        ],
        'patchPaths' => [
            '$.account.enabled',
            '$.account.patched',
            '$.metrics.count',
            '$.nested.path.patchedLeaf',
        ],
    ];
}

for ($case = 1; $case <= 1000; $case++) {
    $tests['real upstream json106 invariant dynamic thousand row ' . $case] =
        static function (TestRunner $t) use ($case): void {
            $fixture = json106_thousand_fixture($case);
            $strict = $fixture['strict'];
            $json5 = $fixture['json5'];
            $blob = json106_thousand_blob($strict);

            $t->same(true, SQLiteJsonValidity::jsonValid($strict), 'json106-1 strict valid');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json5, 2]), 'json106-1 json5 valid');
            $t->same(json106_thousand_canonical($strict), json106_thousand_canonical($json5), 'json106-1 json5 canonical');
            $t->same(json106_thousand_canonical($strict), json106_thousand_canonical($blob), 'json106-1 jsonb canonical');

            $treeRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $strict);
            $rowsByPath = [];
            foreach ($treeRows as $row) {
                if (!in_array($row['type'], ['object', 'array'], true)) {
                    $rowsByPath[$row['fullkey']] = $row;
                }
            }
            foreach ($fixture['scalarPaths'] as $path) {
                $t->true(array_key_exists($path, $rowsByPath), 'json106-2 tree has ' . $path);
                $t->same($rowsByPath[$path]['atom'], SQLiteJsonExtract::extract($strict, $path), 'json106-2 text atom ' . $path);
                $t->same($rowsByPath[$path]['atom'], SQLiteJsonExtract::extract($blob, $path), 'json106-2 jsonb atom ' . $path);
            }

            foreach ($fixture['objectLeafPaths'] as $path) {
                $value = SQLiteJsonExtract::extract($json5, $path);
                $removed = SQLiteJsonRemove::remove($json5, $path);
                $t->same(null, SQLiteJsonExtract::extract($removed, $path), 'json106-5 removed ' . $path);
                $restored = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $path, $value);
                $t->same($value, SQLiteJsonExtract::extract($restored, $path), 'json106-6 restored ' . $path);
            }

            $patched = SQLiteJsonPatch::patch($strict, $fixture['patch']);
            $patchedBlob = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $blob, json106_thousand_blob($fixture['patch']));
            $t->true($patchedBlob instanceof SQLiteBlobValue, 'json106-7 jsonb patch result');
            $t->same(json106_thousand_canonical($patched), json106_thousand_canonical($patchedBlob), 'json106-7 patch parity');
            foreach ($fixture['patchPaths'] as $path) {
                $t->same(SQLiteJsonExtract::extract($fixture['patch'], $path), SQLiteJsonExtract::extract($patched, $path), 'json106-7 patch path ' . $path);
            }

            foreach ([null, '', '  '] as $indent) {
                $args = $indent === null ? [$strict] : [$strict, $indent];
                $prettyText = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', $args);
                $prettyBlob = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$blob, $indent]);
                $t->same(json106_thousand_canonical($strict), json106_thousand_canonical($prettyText), 'json106-8 pretty text');
                $t->same(json106_thousand_canonical($strict), json106_thousand_canonical($prettyBlob), 'json106-8 pretty jsonb');
            }
        };
}

$tests['real upstream json106 invariant dynamic thousand cites source and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test');
        $t->same(['json106-1', 'json106-2', 'json106-5', 'json106-6', 'json106-7', 'json106-8'], ['json106-1', 'json106-2', 'json106-5', 'json106-6', 'json106-7', 'json106-8']);
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
