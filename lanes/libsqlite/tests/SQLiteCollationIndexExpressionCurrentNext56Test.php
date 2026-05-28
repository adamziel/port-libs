<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteExpressionIndexCollationCursor;

$tests = [];

$normalizeSlug = static function (string $value): string {
    return str_replace('_', '-', strtolower(rtrim($value, ' ')));
};
$wpslug = static fn (string $left, string $right): int => strcmp($normalizeSlug($left), $normalizeSlug($right));
$reverse = static fn (string $left, string $right): int => strcmp(strrev($left), strrev($right));

$terms = [
    ['expression' => 'autoload', 'collation' => 'BINARY'],
    ['expression' => 'lower(option_name)', 'collation' => 'WPSLUG'],
    ['expression' => 'substr(option_name,1,8)', 'collation' => 'RTRIM'],
    ['expression' => 'length(option_value)', 'affinity' => 'INTEGER'],
];

$entries = [
    ['key' => ['yes', 'plugin_mode', 'plugin  ', '08'], 'rowid' => 4, 'payload' => ['option_name' => 'Plugin_Mode', 'option_value' => 'autoload']],
    ['key' => ['yes', 'plugin-mode', 'plugin', 8], 'rowid' => 2, 'payload' => ['option_name' => 'plugin-mode', 'option_value' => 'same-len']],
    ['key' => ['yes', 'plugin-mode ', 'plugin  ', '9'], 'rowid' => 7, 'payload' => ['option_name' => 'plugin-mode ', 'option_value' => 'nine-len!']],
    ['key' => ['yes', 'plugin_beta', 'Plugin_B', 4], 'rowid' => 9, 'payload' => ['option_name' => 'Plugin_Beta', 'option_value' => 'beta']],
    ['key' => ['no', 'theme-mode', 'theme   ', 5], 'rowid' => 11, 'payload' => ['option_name' => 'theme-mode', 'option_value' => 'theme']],
    ['key' => ['yes', 'siteurl', 'siteurl  ', 19], 'rowid' => 1, 'payload' => ['option_name' => 'siteurl', 'option_value' => 'https://example.test']],
];

$makeCursor = static fn (?array $cursorTerms = null, array $custom = []): SQLiteExpressionIndexCollationCursor => new SQLiteExpressionIndexCollationCursor(
    $entries,
    $cursorTerms ?? $terms,
    $custom + ['WPSLUG' => $wpslug],
);

$planCases = [
    'first sorted row is no-autoload theme bucket' => [0, 'currentRowid', 11],
    'first next row starts yes autoload group' => [0, 'nextRowid', 9],
    'first boundary is autoload expression' => [0, 'decidingExpression', 'autoload'],
    'first boundary uses binary collation' => [0, 'decidingCollation', 'BINARY'],
    'plugin peer current uses beta before mode under custom collation' => [1, 'currentRowid', 9],
    'plugin peer next keeps lower rowid mode peer' => [1, 'nextRowid', 2],
    'plugin peer comparison decides on lower expression before mode group' => [1, 'comparison', -1],
    'plugin peer has lower expression decision before mode group' => [1, 'decidingExpression', 'lower(option_name)'],
    'second plugin boundary still equals under custom slug collation' => [2, 'comparison', 0],
    'second plugin current keeps dash row' => [2, 'currentRowid', 2],
    'second plugin next keeps underscore row by rowid tie' => [2, 'nextRowid', 4],
    'third plugin boundary decides on length expression' => [3, 'decidingExpression', 'length(option_value)'],
    'third plugin boundary length comparison descends to beta after same prefix' => [3, 'comparison', -1],
    'third plugin boundary collation is binary for numeric length slot' => [3, 'decidingCollation', 'BINARY'],
    'padded mode to siteurl boundary decides on lower expression' => [4, 'decidingExpression', 'lower(option_name)'],
    'padded mode to siteurl boundary uses custom slug collation' => [4, 'decidingCollation', 'WPSLUG'],
    'final row has no next peer' => [5, 'nextRowid', null],
    'final row comparison is null' => [5, 'comparison', null],
    'past eof reports eof' => [6, 'eof', true],
    'past eof current rowid null' => [6, 'currentRowid', null],
];

foreach ($planCases as $name => [$advance, $field, $expected]) {
    $tests['collation index expression current next56 ' . $name] = static function (TestRunner $t) use ($makeCursor, $advance, $field, $expected): void {
        $cursor = $makeCursor();
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }

        $t->same($expected, $cursor->currentNextPlan()[$field]);
    };
}

$seekCases = [
    'custom slug probe matches dash underscore and padded peers' => [['yes', 'PLUGIN-MODE'], [2, 4, 7]],
    'custom slug probe with underscore matches same peer group' => [['yes', 'plugin_mode'], [2, 4, 7]],
    'custom slug probe with trailing space still matches peer group' => [['yes', 'plugin-mode '], [2, 4, 7]],
    'prefix autoload yes returns all yes rows in expression order' => [['yes'], [9, 2, 4, 7, 1]],
    'rtrim prefix narrows to plugin first-eight chars' => [['yes', 'plugin-mode', 'plugin'], [2, 4, 7]],
    'numeric length text probe matches integer length slot' => [['yes', 'plugin-mode', 'plugin', 8], [2, 4]],
    'numeric length string probe matches integer length slot' => [['yes', 'plugin-mode', 'plugin', '08'], [2, 4]],
    'numeric length nine excludes length eight peers' => [['yes', 'plugin-mode', 'plugin', 9], [7]],
    'siteurl custom seek reaches final row' => [['yes', 'siteurl'], [1]],
    'theme no-autoload seek reaches theme row' => [['no', 'theme-mode'], [11]],
    'missing plugin z probe returns empty' => [['yes', 'plugin-z'], []],
    'null probe sorts before text within yes bucket and yields none' => [['yes', null], []],
];

foreach ($seekCases as $name => [$probe, $expectedRowids]) {
    $tests['collation index expression current next56 ' . $name] = static function (TestRunner $t) use ($makeCursor, $probe, $expectedRowids): void {
        $cursor = $makeCursor();
        $t->same($expectedRowids, array_column($cursor->yieldEqual($probe), 'rowid'));
    };
}

$metadataCases = [
    'parser keeps lower expression custom collation' => [static fn () => SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE WPSLUG, autoload)'), 'collation', 'WPSLUG'],
    'parser keeps lower expression descending flag' => [static fn () => SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE WPSLUG DESC, autoload)'), 'descending', true],
    'parser exposes next ordinary term after first expression' => [static fn () => SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE WPSLUG, autoload COLLATE RTRIM DESC)'), '0.columnName', 'autoload'],
    'parser exposes next ordinary term collation after first expression' => [static fn () => SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE WPSLUG, autoload COLLATE RTRIM DESC)'), '0.collation', 'RTRIM'],
    'parser exposes next ordinary term direction after first expression' => [static fn () => SQLiteCreateIndex::columnsAfterFirstExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE WPSLUG, autoload COLLATE RTRIM DESC)'), '0.descending', true],
    'parser rejects missing custom collation token' => [static fn () => SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE, autoload)'), 'value', null],
];

foreach ($metadataCases as $name => [$producer, $path, $expected]) {
    $tests['collation index expression current next56 ' . $name] = static function (TestRunner $t) use ($producer, $path, $expected): void {
        $value = $producer();
        foreach (explode('.', $path) as $part) {
            if ($part === 'value') {
                break;
            }
            $value = is_array($value) ? $value[(int) $part] : $value->{$part};
        }

        $t->same($expected, $value);
    };
}

$customCases = [
    'alternate reverse collation decides on reversed text current' => [
        [
            ['key' => ['abc'], 'rowid' => 1],
            ['key' => ['cba'], 'rowid' => 2],
            ['key' => ['bbb'], 'rowid' => 3],
        ],
        [['expression' => 'reverse(option_name)', 'collation' => 'WPREV']],
        ['WPREV' => $reverse],
        [2, 3, 1],
    ],
    'nocase builtin keeps ascii peers adjacent' => [
        [
            ['key' => ['Plugin'], 'rowid' => 3],
            ['key' => ['plugin'], 'rowid' => 2],
            ['key' => ['theme'], 'rowid' => 5],
        ],
        [['expression' => 'lower(option_name)', 'collation' => 'NOCASE']],
        [],
        [2, 3, 5],
    ],
    'rtrim builtin keeps padded peers adjacent' => [
        [
            ['key' => ['cache  '], 'rowid' => 4],
            ['key' => ['cache'], 'rowid' => 1],
            ['key' => ['cache x'], 'rowid' => 9],
        ],
        [['expression' => 'rtrim(option_name)', 'collation' => 'RTRIM']],
        [],
        [1, 4, 9],
    ],
    'descending expression reverses scan order' => [
        [
            ['key' => ['a'], 'rowid' => 1],
            ['key' => ['c'], 'rowid' => 3],
            ['key' => ['b'], 'rowid' => 2],
        ],
        [['expression' => 'lower(option_name)', 'collation' => 'BINARY', 'descending' => true]],
        [],
        [3, 2, 1],
    ],
];

foreach ($customCases as $name => [$caseEntries, $caseTerms, $custom, $expected]) {
    $tests['collation index expression current next56 ' . $name] = static function (TestRunner $t) use ($caseEntries, $caseTerms, $custom, $expected): void {
        $cursor = new SQLiteExpressionIndexCollationCursor($caseEntries, $caseTerms, $custom);
        $rowids = [];
        while (!$cursor->eof()) {
            $rowids[] = $cursor->currentNextPlan()['currentRowid'];
            $cursor->next();
        }

        $t->same($expected, $rowids);
    };
}

$errorCases = [
    'rejects empty term metadata' => static fn () => new SQLiteExpressionIndexCollationCursor([], []),
    'rejects unknown collation without callback' => static fn () => (new SQLiteExpressionIndexCollationCursor([['key' => ['a'], 'rowid' => 1], ['key' => ['b'], 'rowid' => 2]], [['expression' => 'lower(option_name)', 'collation' => 'WPMISSING']]))->currentNextPlan(),
    'rejects non-list entries' => static fn () => new SQLiteExpressionIndexCollationCursor(['bad' => ['key' => ['a']]], [['expression' => 'lower(option_name)']]),
    'rejects narrow entry keys' => static fn () => new SQLiteExpressionIndexCollationCursor([['key' => ['a']]], [['expression' => 'a'], ['expression' => 'b']]),
    'rejects empty probe' => static fn () => $makeCursor()->yieldEqual([]),
    'rejects wide probe' => static fn () => $makeCursor()->yieldEqual(['yes', 'plugin', 'plugin', 8, 'extra']),
    'rejects non-callable custom collation' => static fn () => new SQLiteExpressionIndexCollationCursor([], [['expression' => 'lower(option_name)']], ['WPSLUG' => 'missing_function']),
    'rejects custom collation non-int return' => static fn () => (new SQLiteExpressionIndexCollationCursor([['key' => ['a'], 'rowid' => 1], ['key' => ['b'], 'rowid' => 2]], [['expression' => 'lower(option_name)', 'collation' => 'WPBAD']], ['WPBAD' => static fn (): string => 'bad']))->currentNextPlan(),
];

foreach ($errorCases as $name => $operation) {
    $tests['collation index expression current next56 ' . $name] = static function (TestRunner $t) use ($operation): void {
        $t->throws(InvalidArgumentException::class, $operation);
    };
}

return $tests;
