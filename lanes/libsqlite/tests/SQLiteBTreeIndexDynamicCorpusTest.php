<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDeleteSequenceCorpusPlan;

$duplicateKeyRecords = static fn (): array => [
    [1, 1],
    [1, 2],
    [1, 3],
    [1, 4],
    [1, 5],
    [1, 6],
    [1, 7],
    [1, 8],
    [1, 9],
    [2, 0],
];

$deleteEvenDuplicates = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [1, 2],
        [1, 4],
        [1, 6],
        [1, 8],
    ],
    secureDelete: true,
);

$deleteTail = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [1, 3],
        [1, 4],
        [1, 5],
        [1, 6],
        [1, 7],
        [1, 8],
        [1, 9],
        [2, 0],
    ],
    secureDelete: true,
);

$deleteFirstDuplicate = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [1, 1],
    ],
    secureDelete: true,
);

$deleteAllDuplicates = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [1, 1],
        [1, 2],
        [1, 3],
        [1, 4],
        [1, 5],
        [1, 6],
        [1, 7],
        [1, 8],
        [1, 9],
        [2, 0],
    ],
    secureDelete: true,
);

$deleteDescendingDuplicates = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [2, 0],
        [1, 9],
        [1, 8],
        [1, 7],
        [1, 6],
        [1, 5],
        [1, 4],
        [1, 3],
        [1, 2],
        [1, 1],
    ],
    secureDelete: true,
);

$deleteInteriorRun = static fn (): array => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence(
    $duplicateKeyRecords(),
    [
        [1, 4],
        [1, 5],
        [1, 6],
    ],
    secureDelete: true,
);

$tests = [];

$evenCases = [
    'upstream source cites index test range' => ['upstream_source', 'SQLite upstream test/index.test scenarios index-10.4 through index-10.8'],
    'non overlap names dynamic duplicate-key deletes' => [static fn (array $plan): bool => str_contains($plan['non_overlap'], 'dynamic duplicate-key delete'), true],
    'dependency closure reuses index leaf deletion' => [static fn (array $plan): bool => str_contains($plan['dependency_closure'], 'SQLiteIndexLeafPage'), true],
    'initial cell count follows upstream ten inserted rows' => ['snapshots.0.cell_count', 10],
    'final cell count removes four even duplicate rows' => [static fn (array $plan): int => $plan['snapshots'][4]['cell_count'], 6],
    'remaining row order preserves duplicate key order' => ['remaining_records', [[1, 1], [1, 3], [1, 5], [1, 7], [1, 9], [2, 0]]],
    'deleted rows are recorded in statement order' => ['deleted_records', [[1, 2], [1, 4], [1, 6], [1, 8]]],
    'first delete removes only b equals two' => ['snapshots.1.records', [[1, 1], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'second delete keeps odd duplicate values' => ['snapshots.2.records', [[1, 1], [1, 3], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'third delete keeps high duplicate values' => ['snapshots.3.records', [[1, 1], [1, 3], [1, 5], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'fourth delete leaves terminal nonduplicate' => ['snapshots.4.records', [[1, 1], [1, 3], [1, 5], [1, 7], [1, 9], [2, 0]]],
    'initial integrity ok' => ['snapshots.0.integrity_status', 'ok'],
    'first delete integrity ok' => ['snapshots.1.integrity_status', 'ok'],
    'second delete integrity ok' => ['snapshots.2.integrity_status', 'ok'],
    'third delete integrity ok' => ['snapshots.3.integrity_status', 'ok'],
    'fourth delete integrity ok' => ['snapshots.4.integrity_status', 'ok'],
    'first delete creates reusable freeblock' => [static fn (array $plan): bool => $plan['snapshots'][1]['freeblock_count'] >= 1, true],
    'free space grows after first delete' => [static fn (array $plan): bool => $plan['snapshots'][1]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
    'free space grows after fourth delete' => [static fn (array $plan): bool => $plan['snapshots'][4]['free_space_bytes'] > $plan['snapshots'][1]['free_space_bytes'], true],
    'page bytes remain nonempty' => [static fn (array $plan): bool => strlen($plan['page']) === 512, true],
];

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[(int) $segment == (string) $segment ? (int) $segment : $segment];
    }

    return $value;
};

foreach ($evenCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 duplicate delete ' . $name] = static function (TestRunner $t) use ($deleteEvenDuplicates, $selector, $expected, $valueAt): void {
        $plan = $deleteEvenDuplicates();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$tailCases = [
    'tail delete final records match upstream predicate b greater than two' => ['remaining_records', [[1, 1], [1, 2]]],
    'tail delete final cell count is two' => [static fn (array $plan): int => $plan['snapshots'][8]['cell_count'], 2],
    'tail delete deleted records preserve ascending scan order' => ['deleted_records', [[1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'tail delete first snapshot label' => ['snapshots.1.label', 'delete-1'],
    'tail delete final snapshot label' => ['snapshots.8.label', 'delete-8'],
    'tail delete first snapshot keeps b equals two' => ['snapshots.1.records', [[1, 1], [1, 2], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'tail delete penultimate snapshot has terminal row' => ['snapshots.7.records', [[1, 1], [1, 2], [2, 0]]],
    'tail delete final integrity ok' => ['snapshots.8.integrity_status', 'ok'],
    'tail delete free space grows monotonically overall' => [static fn (array $plan): bool => $plan['snapshots'][8]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
    'tail delete final freeblock count nonzero' => [static fn (array $plan): bool => $plan['snapshots'][8]['freeblock_count'] >= 1, true],
];

foreach ($tailCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 range delete ' . $name] = static function (TestRunner $t) use ($deleteTail, $selector, $expected, $valueAt): void {
        $plan = $deleteTail();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$firstCases = [
    'single delete removes leading duplicate' => ['remaining_records', [[1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'single delete records deleted key' => ['deleted_records', [[1, 1]]],
    'single delete final cell count is nine' => ['snapshots.1.cell_count', 9],
    'single delete final integrity ok' => ['snapshots.1.integrity_status', 'ok'],
    'single delete frees bytes' => [static fn (array $plan): bool => $plan['snapshots'][1]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
];

foreach ($firstCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 point delete ' . $name] = static function (TestRunner $t) use ($deleteFirstDuplicate, $selector, $expected, $valueAt): void {
        $plan = $deleteFirstDuplicate();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$allCases = [
    'delete all leaves empty record set' => ['remaining_records', []],
    'delete all final cell count zero' => ['snapshots.10.cell_count', 0],
    'delete all final integrity ok' => ['snapshots.10.integrity_status', 'ok'],
    'delete all records every deleted row' => ['deleted_records', [[1, 1], [1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'delete all first snapshot removes leading duplicate' => ['snapshots.1.records', [[1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'delete all fifth snapshot keeps upper run' => ['snapshots.5.records', [[1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'delete all ninth snapshot keeps terminal row' => ['snapshots.9.records', [[2, 0]]],
    'delete all free space grows overall' => [static fn (array $plan): bool => $plan['snapshots'][10]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
    'delete all has eleven snapshots including initial' => [static fn (array $plan): int => count($plan['snapshots']), 11],
];

foreach ($allCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 full delete ' . $name] = static function (TestRunner $t) use ($deleteAllDuplicates, $selector, $expected, $valueAt): void {
        $plan = $deleteAllDuplicates();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$descendingCases = [
    'descending delete leaves empty record set' => ['remaining_records', []],
    'descending delete preserves requested delete order' => ['deleted_records', [[2, 0], [1, 9], [1, 8], [1, 7], [1, 6], [1, 5], [1, 4], [1, 3], [1, 2], [1, 1]]],
    'descending first delete removes nonduplicate terminal row' => ['snapshots.1.records', [[1, 1], [1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9]]],
    'descending fifth delete keeps low duplicate prefix' => ['snapshots.5.records', [[1, 1], [1, 2], [1, 3], [1, 4], [1, 5]]],
    'descending ninth delete keeps first duplicate' => ['snapshots.9.records', [[1, 1]]],
    'descending final cell count zero' => ['snapshots.10.cell_count', 0],
    'descending final integrity ok' => ['snapshots.10.integrity_status', 'ok'],
    'descending free space grows overall' => [static fn (array $plan): bool => $plan['snapshots'][10]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
];

foreach ($descendingCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 descending delete ' . $name] = static function (TestRunner $t) use ($deleteDescendingDuplicates, $selector, $expected, $valueAt): void {
        $plan = $deleteDescendingDuplicates();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$interiorCases = [
    'interior run leaves low and high duplicate ranges' => ['remaining_records', [[1, 1], [1, 2], [1, 3], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'interior run final cell count seven' => ['snapshots.3.cell_count', 7],
    'interior run deleted records preserve scan order' => ['deleted_records', [[1, 4], [1, 5], [1, 6]]],
    'interior run first delete keeps adjacent duplicates' => ['snapshots.1.records', [[1, 1], [1, 2], [1, 3], [1, 5], [1, 6], [1, 7], [1, 8], [1, 9], [2, 0]]],
    'interior run final integrity ok' => ['snapshots.3.integrity_status', 'ok'],
    'interior run free space grows overall' => [static fn (array $plan): bool => $plan['snapshots'][3]['free_space_bytes'] > $plan['snapshots'][0]['free_space_bytes'], true],
];

foreach ($interiorCases as $name => [$selector, $expected]) {
    $tests['upstream index.test 10 interior delete ' . $name] = static function (TestRunner $t) use ($deleteInteriorRun, $selector, $expected, $valueAt): void {
        $plan = $deleteInteriorRun();
        $actual = is_string($selector) ? $valueAt($plan, $selector) : $selector($plan);
        $t->same($expected, $actual);
    };
}

$tests['upstream index.test dynamic corpus rejects empty source records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence([], [[1, 1]]));
};

$tests['upstream index.test dynamic corpus rejects empty deletion sequence'] = static function (TestRunner $t) use ($duplicateKeyRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence($duplicateKeyRecords(), []));
};

$tests['upstream index.test dynamic corpus rejects missing indexed record'] = static function (TestRunner $t) use ($duplicateKeyRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence($duplicateKeyRecords(), [[99, 99]]));
};

return $tests;
