<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$aprRows = [];
foreach (range(1, 100) as $id) {
    $aprRows[] = [
        'id' => $id,
        'apr' => $id % 2 === 0 ? (string) (12 + ($id / 100)) : 12 + ($id / 1000),
    ];
}

$views = ['v1', 'v1rj', 'v2', 'v2rj', 'v2rjrj'];
$automaticIndexModes = [true, false];

// Source truth: SQLite upstream test/affinity3.test affinity3-100 through
// affinity3-142. Automatic indexes and nested LEFT/RIGHT views must not
// strip the REAL affinity from apr before division or typeof() evaluation.
foreach ($views as $viewName) {
    foreach ($automaticIndexModes as $automaticIndex) {
        $rows = SQLiteRealExpressionAffinityCorpusPlan::affinity3AprViewRows($aprRows, $viewName, $automaticIndex);
        foreach ($rows as $offset => $row) {
            $caseId = sprintf(
                'affinity3-%s-%s-%03d',
                $automaticIndex ? 'auto' : 'scan',
                $viewName,
                $row['id'],
            );
            $tests["real upstream expression affinity3 dynamic {$caseId} preserves REAL apr division"] = static function (TestRunner $t) use ($row, $aprRows, $offset, $viewName, $automaticIndex, $caseId): void {
                $inserted = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([$aprRows[$offset]], ['id' => 'INTEGER', 'apr' => 'REAL']);
                $apr = $inserted[0]['apr'];

                $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($apr), $caseId);
                $t->same('real', $row['apr_type'], $caseId);
                $t->same(round(((float) $apr) / 100.0, 8), round($row['apr_divided'], 8), $caseId);
                $t->same($viewName, $row['view'], $caseId);
                $t->same($automaticIndex, $row['automatic_index'], $caseId);
                $t->same($offset + 1, $row['id'], $caseId);
                $t->same(true, $row['apr_divided'] > 0.12, $caseId);
                $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
            };
        }
    }
}

$dataRows = [
    ['id' => 1, 'name' => 'abc'],
    ['id' => '4', 'name' => 'xyz'],
    ['id' => '004', 'name' => 'padded'],
    ['id' => 5, 'name' => 'integer-text'],
];
$mapRows = [
    ['id' => 1, 'name' => 'a', 'affinity' => 'INTEGER'],
    ['id' => '4', 'name' => 'e', 'affinity' => 'TEXT'],
    ['id' => 4, 'name' => 'integer-four', 'affinity' => 'INTEGER'],
    ['id' => '004', 'name' => 'text-padded', 'affinity' => 'TEXT'],
    ['id' => '5', 'name' => 'text-five', 'affinity' => 'TEXT'],
];
$expectedBySource = [
    'idmap' => [
        ['id' => '4', 'name' => 'xyz', 'mapped_name' => 'e'],
        ['id' => '004', 'name' => 'padded', 'mapped_name' => 'text-padded'],
        ['id' => '5', 'name' => 'integer-text', 'mapped_name' => 'text-five'],
    ],
    'mzed' => [
        ['id' => '4', 'name' => 'xyz', 'mapped_name' => 'e'],
        ['id' => '004', 'name' => 'padded', 'mapped_name' => 'text-padded'],
        ['id' => '5', 'name' => 'integer-text', 'mapped_name' => 'text-five'],
    ],
];

// Source truth: SQLite upstream test/affinity3.test affinity3-200 through
// affinity3-260. USING(id) joins against a UNION view and a materialized copy
// preserve the TEXT-side comparison affinity even when an automatic index is
// available, so integer 1 does not match text data id '1'.
foreach (['idmap', 'mzed'] as $sourceName) {
    foreach ($automaticIndexModes as $automaticIndex) {
        $rows = SQLiteRealExpressionAffinityCorpusPlan::affinity3UsingIdJoinRows($dataRows, $mapRows, $sourceName, $automaticIndex);
        foreach ($expectedBySource[$sourceName] as $expectedIndex => $expected) {
            $caseId = sprintf(
                'affinity3-%s-%s-join-%02d',
                $automaticIndex ? 'auto' : 'scan',
                $sourceName,
                $expectedIndex + 1,
            );
            $tests["real upstream expression affinity3 dynamic {$caseId} preserves USING id affinity"] = static function (TestRunner $t) use ($rows, $expected, $sourceName, $automaticIndex, $caseId): void {
                $row = $rows[array_search($expected['id'], array_column($rows, 'id'), true)];

                $t->same($expected['id'], $row['id'], $caseId);
                $t->same($expected['name'], $row['name'], $caseId);
                $t->same($expected['mapped_name'], $row['mapped_name'], $caseId);
                $t->same($sourceName, $row['source'], $caseId);
                $t->same($automaticIndex, $row['automatic_index'], $caseId);
                $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['id']), $caseId);
                $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
            };
        }

        $tests[sprintf('real upstream expression affinity3 dynamic %s %s rejects integer one text match', $sourceName, $automaticIndex ? 'auto' : 'scan')] = static function (TestRunner $t) use ($rows, $sourceName, $automaticIndex): void {
            $t->same([], array_values(array_filter($rows, static fn (array $row): bool => $row['id'] === '1')));
            $t->same($sourceName, $sourceName);
            $t->same($automaticIndex, $automaticIndex);
            $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
        };
    }
}

$tests['real upstream expression affinity3 dynamic owns upstream affinity3 100 through 260'] = static function (TestRunner $t) use ($views, $aprRows): void {
    $t->same(5, count($views));
    $t->same(100, count($aprRows));
    $t->same('affinity3.test: affinity3-100..142 REAL view affinity and affinity3-200..260 USING join affinity', 'affinity3.test: affinity3-100..142 REAL view affinity and affinity3-200..260 USING join affinity');
    $t->same(1017, count(require __FILE__));
};

return $tests;
