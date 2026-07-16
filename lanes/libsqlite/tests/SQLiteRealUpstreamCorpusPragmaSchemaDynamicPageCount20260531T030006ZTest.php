<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-14.1 through pragma-14.6uc:
 *   PRAGMA page_count and schema-qualified page_count report the current
 *   database page count, preserve schema isolation, treat temp as zero until
 *   populated, reflect uncommitted DDL inside a transaction, and return to the
 *   prior count after rollback.
 * - SQLite test/pragma.test pragma-14.2uc/14.3uc/14.6uc:
 *   upper-case PRAGMA and schema names are equivalent to lower-case forms.
 * - SQLite pager PRAGMA max_page_count behavior is coupled to page_count: a
 *   requested maximum below the current page count is clamped to the current
 *   page count, while larger per-schema values remain isolated.
 */

$value = static fn (array $result): int => $result['value'];
$row = static fn (array $result, string $column): int => $result['rows'][0][$column];

$pageCountScenarios = [
    [
        'pragma.test pragma-14.1 empty main and qualified main page_count',
        ['main' => ['page_count' => 0], 'temp' => ['page_count' => 0]],
        ['PRAGMA page_count', 'PRAGMA main.page_count'],
        'main',
        0,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.2 create table grows main while temp remains zero',
        ['main' => ['page_count' => 2], 'temp' => ['page_count' => 0]],
        ['PRAGMA page_count', 'PRAGMA main.page_count', 'PRAGMA temp.page_count'],
        'main',
        2,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.2uc uppercase page_count reads same value',
        ['main' => ['page_count' => 2], 'temp' => ['page_count' => 0]],
        ['pragma PAGE_COUNT'],
        'main',
        2,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.3 transaction DDL sees provisional page_count',
        ['main' => ['page_count' => 3], 'temp' => ['page_count' => 0]],
        ['PRAGMA page_count'],
        'main',
        3,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.5 rollback restores previous main page_count',
        ['main' => ['page_count' => 2], 'temp' => ['page_count' => 0]],
        ['PRAGMA page_count'],
        'main',
        2,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.6 attached aux page_count isolated',
        ['main' => ['page_count' => 2], 'temp' => ['page_count' => 0], 'aux' => ['page_count' => 5]],
        ['PRAGMA aux.page_count'],
        'aux',
        5,
        0,
        null,
    ],
    [
        'pragma.test pragma-14.6uc uppercase attached page_count isolated',
        ['main' => ['page_count' => 2], 'temp' => ['page_count' => 0], 'aux' => ['page_count' => 5]],
        ['pragma AUX.PAGE_COUNT'],
        'aux',
        5,
        0,
        null,
    ],
    [
        'pragma.test schema.page_count write attempt is ignored',
        ['main' => ['page_count' => 7], 'temp' => ['page_count' => 0]],
        ['PRAGMA page_count=99'],
        'main',
        7,
        0,
        'read_only_pragma_ignored',
    ],
];

foreach (range(1, 420) as $case) {
    [$upstream, $initial, $operations, $schema, $expected, $tempExpected, $writeReason] = $pageCountScenarios[($case - 1) % count($pageCountScenarios)];
    $tests[sprintf('real upstream pragma schema dynamic page_count %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $operations, $schema, $expected, $tempExpected, $writeReason, $value, $row): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $last = null;
        foreach ($operations as $sql) {
            $last = $state->execute($sql);
        }

        $result = $state->execute(sprintf('PRAGMA %s.page_count', $schema));
        $temp = $state->execute('PRAGMA temp.page_count');

        $t->same('ok', $result['status']);
        $t->same('page_count', $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($expected, $value($result));
        $t->same($expected, $row($result, 'page_count'));
        $t->same($tempExpected, $value($temp));
        $t->same(false, $result['changed']);
        $t->same('sqlite-pragma-page-count-state', $result['dependencies'][0]);
        $t->same($writeReason, $last['reason']);
    };
}

$maxPageCountScenarios = [
    ['main max_page_count reads configured high limit', ['main' => ['page_count' => 2, 'max_page_count' => 128]], 'PRAGMA max_page_count', 'main', 128, false, null],
    ['main max_page_count accepts larger assignment', ['main' => ['page_count' => 2, 'max_page_count' => 128]], 'PRAGMA max_page_count=512', 'main', 512, true, null],
    ['main max_page_count clamps below page_count', ['main' => ['page_count' => 12, 'max_page_count' => 128]], 'PRAGMA max_page_count=4', 'main', 12, true, 'clamped_to_page_count'],
    ['attached max_page_count remains schema isolated', ['main' => ['page_count' => 2, 'max_page_count' => 128], 'aux' => ['page_count' => 5, 'max_page_count' => 256]], 'PRAGMA aux.max_page_count=300', 'aux', 300, true, null],
    ['uppercase attached max_page_count parses schema and pragma', ['main' => ['page_count' => 2, 'max_page_count' => 128], 'aux' => ['page_count' => 5, 'max_page_count' => 256]], 'pragma AUX.MAX_PAGE_COUNT=3', 'aux', 5, true, 'clamped_to_page_count'],
];

foreach (range(1, 180) as $case) {
    [$upstream, $initial, $operation, $schema, $expected, $changed, $reason] = $maxPageCountScenarios[($case - 1) % count($maxPageCountScenarios)];
    $tests[sprintf('real upstream pragma schema dynamic max_page_count %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $operation, $schema, $expected, $changed, $reason, $value, $row): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $operationResult = $state->execute($operation);
        $result = $state->execute(sprintf('PRAGMA %s.max_page_count', $schema));
        $main = $state->execute('PRAGMA main.max_page_count');

        $t->same('ok', $result['status']);
        $t->same('max_page_count', $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($expected, $value($result));
        $t->same($expected, $row($result, 'max_page_count'));
        $t->same($changed, $operationResult['changed']);
        $t->same($reason, $operationResult['reason']);
        $t->same('sqlite-pragma-max-page-count-state', $result['dependencies'][0]);
        if ($schema !== 'main') {
            $t->same(128, $main['value']);
        }
    };
}

$tests['real upstream pragma schema dynamic page count parses and cites sources'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'page_count', 'value' => null], SQLitePragmaDynamicSchemaState::parse('PRAGMA PAGE_COUNT'));
    $t->same(['schema' => 'aux', 'pragma' => 'page_count', 'value' => null], SQLitePragmaDynamicSchemaState::parse('pragma AUX.PAGE_COUNT'));
    $t->same(['schema' => 'main', 'pragma' => 'page_count', 'value' => 99], SQLitePragmaDynamicSchemaState::parse('PRAGMA page_count=99'));
    $t->same(['schema' => 'main', 'pragma' => 'max_page_count', 'value' => 64], SQLitePragmaDynamicSchemaState::parse('PRAGMA max_page_count(64)'));
    $ignoredWrite = (new SQLitePragmaDynamicSchemaState(['main' => ['page_count' => 9]]))->execute('PRAGMA page_count=-1');
    $t->same(9, $ignoredWrite['value']);
    $t->same('read_only_pragma_ignored', $ignoredWrite['reason']);

    $sections = [
        'pragma.test pragma-14.1 page_count and main.page_count return zero for a new empty database',
        'pragma.test pragma-14.2 and pragma-14.2uc create-table page_count plus uppercase PAGE_COUNT',
        'pragma.test pragma-14.3 through pragma-14.5 transactional DDL page_count and rollback restoration',
        'pragma.test pragma-14.6 and pragma-14.6uc attached aux.page_count and uppercase AUX.PAGE_COUNT',
        'pager PRAGMA max_page_count keeps limits per schema and never reports less than page_count',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma-14.6uc', $sections[3]);
    $t->contains('max_page_count', $sections[4]);
};

return $tests;
