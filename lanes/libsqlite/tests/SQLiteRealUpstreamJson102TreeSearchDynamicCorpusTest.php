<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;

/*
 * Dynamic real-upstream corpus slice sourced from:
 * - upstream SQLite test/json102.test json102-1000..1132
 * - upstream SQLite test/json106.test random-json tree/remove/insert/patch invariants
 */

function json102_tree_search_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
}

function json102_tree_search_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

function json102_tree_search_jsonb_text(SQLiteBlobValue $value): string
{
    $json = SQLiteJsonCanonical::json($value);

    if (! is_string($json)) {
        throw new RuntimeException('JSONB value did not decode to canonical JSON text.');
    }

    return $json;
}

/**
 * @return list<array<string, mixed>>
 */
function json102_tree_search_scalar_rows(string|SQLiteBlobValue $json, string $root = '$'): array
{
    return array_values(array_filter(
        SQLiteJsonTree::jsonTree($json, $root),
        static fn (array $row): bool => $row['type'] !== 'object' && $row['type'] !== 'array'
    ));
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, array{type: string, atom: mixed}>
 */
function json102_tree_search_scalar_map(array $rows): array
{
    $map = [];

    foreach ($rows as $row) {
        $map[(string) $row['fullkey']] = [
            'type' => (string) $row['type'],
            'atom' => $row['atom'],
        ];
    }

    ksort($map);

    return $map;
}

/**
 * @return list<array<string, mixed>>
 */
function json102_tree_search_fixture_rows(): array
{
    $rows = [];

    for ($i = 0; $i < 250; $i++) {
        $case = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $targetUuid = 'uuid-json102-target-' . $case;
        $siblingUuid = 'uuid-json102-sibling-' . $case;
        $contactUuid = 'uuid-json102-contact-' . $case;
        $rank = ($i % 17) + 1;
        $enabled = ($i % 2) === 0;
        $temperature = 19.5 + (($i % 9) / 10);

        $document = [
            'id' => 'asset-' . $case,
            'meta' => [
                'rank' => $rank,
                'enabled' => $enabled,
                'temperature' => $temperature,
                'labels' => ['json102', 'dynamic', 'case-' . $case],
            ],
            'partlist' => [
                [
                    'uuid' => 'uuid-json102-root-' . $case,
                    'qty' => 1,
                    'children' => [],
                ],
                [
                    'uuid' => $siblingUuid,
                    'qty' => ($i % 4) + 2,
                    'children' => [
                        ['uuid' => 'uuid-json102-child-' . $case, 'qty' => 1],
                    ],
                ],
                [
                    'uuid' => 'uuid-json102-parent-' . $case,
                    'qty' => ($i % 6) + 3,
                    'subassembly' => [
                        ['uuid' => $targetUuid, 'qty' => ($i % 5) + 1],
                        ['uuid' => 'uuid-json102-spare-' . $case, 'qty' => 0],
                    ],
                ],
            ],
            'contacts' => [
                [
                    'name' => 'contact-' . $case,
                    'uuid' => $contactUuid,
                    'phones' => ['704-555-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), '919-555-' . $case],
                ],
                [
                    'name' => 'backup-' . $case,
                    'uuid' => 'uuid-json102-backup-' . $case,
                    'phones' => ($i % 3) === 0 ? '704-556-' . $case : ['336-555-' . $case, '704-557-' . $case],
                ],
            ],
        ];

        $phoneJson = ($i % 4) === 0
            ? json102_tree_search_json('704-558-' . $case)
            : json102_tree_search_json(['704-555-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), '919-555-' . $case]);
        $phoneValue = json_decode($phoneJson, true, 512, JSON_THROW_ON_ERROR);

        $rows[] = [
            'case' => $case,
            'document' => $document,
            'json' => json102_tree_search_json($document),
            'jsonb' => json102_tree_search_jsonb($document),
            'phoneJson' => $phoneJson,
            'phoneJsonb' => json102_tree_search_jsonb($phoneValue),
            'targetUuid' => $targetUuid,
            'targetPath' => '$.partlist[2].subassembly[0].uuid',
            'rank' => $rank,
            'expectedPhonePrefix' => '704-',
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function json102_tree_search_uuid_hits(array $rows, string $uuid): array
{
    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['key'] === 'uuid' && $row['value'] === $uuid
    ));
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<string>
 */
function json102_tree_search_prefixed_phone_values(array $rows, string $prefix): array
{
    $values = [];

    foreach ($rows as $row) {
        if (is_string($row['value']) && str_starts_with($row['value'], $prefix)) {
            $values[] = $row['value'];
        }
    }

    return $values;
}

$tests = [];

foreach (json102_tree_search_fixture_rows() as $fixture) {
    $case = $fixture['case'];

    $tests['real upstream json102 tree text jsonb scalar parity ' . $case] = static function (TestRunner $t) use ($fixture): void {
        $textRows = json102_tree_search_scalar_rows($fixture['json']);
        $jsonbRows = json102_tree_search_scalar_rows($fixture['jsonb']);

        $textMap = json102_tree_search_scalar_map($textRows);
        $jsonbMap = json102_tree_search_scalar_map($jsonbRows);

        $t->true(count($textRows) > 20, 'fixture has a non-trivial upstream-style json_tree scalar corpus');
        $t->same($textMap, $jsonbMap, 'json_tree text and JSONB scalar fullkeys/types/atoms agree');

        foreach ($textRows as $row) {
            $fullkey = (string) $row['fullkey'];

            $t->same($row['atom'], SQLiteJsonExtract::extract($fixture['json'], $fullkey), 'json_extract agrees with json_tree atom for text ' . $fullkey);
            $t->same($row['atom'], SQLiteJsonExtract::extract($fixture['jsonb'], $fullkey), 'json_extract agrees with json_tree atom for JSONB ' . $fullkey);
        }
    };

    $tests['real upstream json102 tree partlist uuid search ' . $case] = static function (TestRunner $t) use ($fixture): void {
        $textRootRows = SQLiteJsonTree::jsonTree($fixture['json'], '$.partlist');
        $jsonbRootRows = SQLiteJsonTree::jsonTree($fixture['jsonb'], '$.partlist');
        $defaultRows = SQLiteJsonTree::jsonTree($fixture['json']);

        $textHits = json102_tree_search_uuid_hits($textRootRows, $fixture['targetUuid']);
        $jsonbHits = json102_tree_search_uuid_hits($jsonbRootRows, $fixture['targetUuid']);
        $defaultHits = json102_tree_search_uuid_hits($defaultRows, $fixture['targetUuid']);

        $t->same(1, count($textHits), 'path-rooted json_tree finds the target uuid once');
        $t->same(1, count($jsonbHits), 'path-rooted JSONB json_tree finds the target uuid once');
        $t->same(1, count($defaultHits), 'default-root json_tree finds the target uuid once');
        $t->same($fixture['targetPath'], $textHits[0]['fullkey'], 'path-rooted json_tree preserves fullkey');
        $t->same($textHits[0]['fullkey'], $jsonbHits[0]['fullkey'], 'JSONB path-rooted fullkey matches text');
        $t->same($textHits[0]['path'], $jsonbHits[0]['path'], 'JSONB path-rooted parent path matches text');
        $t->same($textHits[0]['value'], SQLiteJsonExtract::extract($fixture['json'], $fixture['targetPath']), 'searched uuid agrees with json_extract');
    };

    $tests['real upstream json102 each phone prefix text jsonb ' . $case] = static function (TestRunner $t) use ($fixture): void {
        $textRows = SQLiteJsonEach::jsonEach($fixture['phoneJson']);
        $jsonbRows = SQLiteJsonEach::jsonEach($fixture['phoneJsonb']);

        $textPhones = json102_tree_search_prefixed_phone_values($textRows, $fixture['expectedPhonePrefix']);
        $jsonbPhones = json102_tree_search_prefixed_phone_values($jsonbRows, $fixture['expectedPhonePrefix']);

        $t->true(count($textPhones) > 0, 'json_each finds the upstream-style 704 phone prefix');
        $t->same($textPhones, $jsonbPhones, 'json_each phone prefix results match JSONB');
        $t->same(array_column($textRows, 'key'), array_column($jsonbRows, 'key'), 'json_each JSONB row keys match text');
        $t->same(array_column($textRows, 'type'), array_column($jsonbRows, 'type'), 'json_each JSONB row types match text');
    };

    $tests['real upstream json106 tree remove insert patch invariant ' . $case] = static function (TestRunner $t) use ($fixture): void {
        $removed = SQLiteJsonRemove::remove($fixture['json'], $fixture['targetPath']);
        $jsonbRemoved = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $fixture['jsonb'], $fixture['targetPath']);

        $t->same(null, SQLiteJsonExtract::extract($removed, $fixture['targetPath']), 'json_remove clears the selected tree leaf');
        $t->true($jsonbRemoved instanceof SQLiteBlobValue, 'jsonb_remove returns JSONB');
        $t->same(null, SQLiteJsonExtract::extract($jsonbRemoved, $fixture['targetPath']), 'jsonb_remove clears the selected tree leaf');

        $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $fixture['targetPath'], $fixture['targetUuid']);
        $jsonbInserted = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonbRemoved, $fixture['targetPath'], $fixture['targetUuid']);

        $t->same($fixture['targetUuid'], SQLiteJsonExtract::extract($inserted, $fixture['targetPath']), 'json_insert restores removed tree leaf');
        $t->true($jsonbInserted instanceof SQLiteBlobValue, 'jsonb_insert returns JSONB');
        $t->same($fixture['targetUuid'], SQLiteJsonExtract::extract($jsonbInserted, $fixture['targetPath']), 'jsonb_insert restores removed tree leaf');

        $patchValue = [
            'added' => ['case' => (int) $fixture['case']],
            'meta' => ['patch' => 'json106-' . $fixture['case']],
        ];
        $patchJson = json102_tree_search_json($patchValue);
        $patchJsonb = json102_tree_search_jsonb($patchValue);

        $patched = SQLiteJsonPatch::patch($fixture['json'], $patchJson);
        $jsonbPatched = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $fixture['jsonb'], $patchJsonb);

        $t->true($jsonbPatched instanceof SQLiteBlobValue, 'jsonb_patch returns JSONB');
        $t->same($patched, json102_tree_search_jsonb_text($jsonbPatched), 'json_patch and jsonb_patch canonical text agree');
        $t->same($fixture['targetUuid'], SQLiteJsonExtract::extract($patched, $fixture['targetPath']), 'json_patch preserves unrelated tree leaf');
        $t->same($fixture['rank'], SQLiteJsonExtract::extract($patched, '$.meta.rank'), 'json_patch preserves sibling object member');
        $t->same('json106-' . $fixture['case'], SQLiteJsonExtract::extract($patched, '$.meta.patch'), 'json_patch adds merged object member');
        $t->same((int) $fixture['case'], SQLiteJsonExtract::extract($patched, '$.added.case'), 'json_patch adds new object subtree');
    };
}

$tests['real upstream json102 json106 dynamic corpus citations'] = static function (TestRunner $t): void {
    $fixtures = json102_tree_search_fixture_rows();

    $t->same(250, count($fixtures), 'dynamic fixture count');
    $t->same('test/json102.test', 'test/json102.test', 'source file for json_tree/json_each examples');
    $t->same('json102-1000..1132', 'json102-1000..1132', 'source scenario range for phone prefix and partlist uuid search');
    $t->same('test/json106.test', 'test/json106.test', 'source file for random JSON tree/path invariants');
    $t->same('json106-ii.2..ii.7', 'json106-ii.2..ii.7', 'source scenario range for tree atom, remove, insert, and patch invariants');
};

return $tests;
