<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLikeCurrentNextCursor;

$tests = [];

$entries = [
    ['key' => 'siteurl', 'rowid' => 1, 'payload' => ['option_name' => 'siteurl', 'autoload' => 'yes']],
    ['key' => 'SiteURL', 'rowid' => 2, 'payload' => ['option_name' => 'SiteURL', 'autoload' => 'no']],
    ['key' => 'siteurl ', 'rowid' => 3, 'payload' => ['option_name' => 'siteurl ', 'autoload' => 'no']],
    ['key' => 'site_admin_email', 'rowid' => 4, 'payload' => ['option_name' => 'site_admin_email', 'autoload' => 'yes']],
    ['key' => 'Site_Admin_Email', 'rowid' => 5, 'payload' => ['option_name' => 'Site_Admin_Email', 'autoload' => 'yes']],
    ['key' => 'sitedebug', 'rowid' => 6, 'payload' => ['option_name' => 'sitedebug', 'autoload' => 'no']],
    ['key' => 'plugin_100%_enabled', 'rowid' => 7, 'payload' => ['option_name' => 'plugin_100%_enabled', 'autoload' => 'yes']],
    ['key' => 'Plugin_100%_Enabled', 'rowid' => 8, 'payload' => ['option_name' => 'Plugin_100%_Enabled', 'autoload' => 'no']],
    ['key' => 'plugin_100x_enabled', 'rowid' => 9, 'payload' => ['option_name' => 'plugin_100x_enabled', 'autoload' => 'yes']],
    ['key' => 'plugin_100%_enabled_beta', 'rowid' => 10, 'payload' => ['option_name' => 'plugin_100%_enabled_beta', 'autoload' => 'no']],
    ['key' => 'plugin_200%_enabled', 'rowid' => 11, 'payload' => ['option_name' => 'plugin_200%_enabled', 'autoload' => 'yes']],
    ['key' => 'é_plugin', 'rowid' => 12, 'payload' => ['option_name' => 'é_plugin', 'autoload' => 'yes']],
    ['key' => 'É_plugin', 'rowid' => 13, 'payload' => ['option_name' => 'É_plugin', 'autoload' => 'no']],
    ['key' => 'zz_plugin', 'rowid' => 14, 'payload' => ['option_name' => 'zz_plugin', 'autoload' => 'no']],
];

$makeCursor = static fn (string $pattern = 'site%', string $collation = 'NOCASE', ?string $escape = null, bool $caseSensitive = false): SQLiteLikeCurrentNextCursor => new SQLiteLikeCurrentNextCursor(
    $entries,
    $pattern,
    $collation,
    $escape,
    $caseSensitive,
);

$planCases = [
    'nocase range keeps folded first current rowid' => ['site%', 'NOCASE', null, false, 0, 'currentRowid', 4],
    'nocase range next row is folded admin peer' => ['site%', 'NOCASE', null, false, 0, 'nextRowid', 5],
    'nocase range lower bound is site' => ['site%', 'NOCASE', null, false, 0, 'range.lowerInclusive', 'site'],
    'nocase range upper bound is sitf' => ['site%', 'NOCASE', null, false, 0, 'range.upperBound', 'sitf'],
    'nocase first row is inside range' => ['site%', 'NOCASE', null, false, 0, 'inRange', true],
    'nocase first row residual matches' => ['site%', 'NOCASE', null, false, 0, 'residualMatch', true],
    'nocase first next row residual matches' => ['site%', 'NOCASE', null, false, 0, 'nextResidualMatch', true],
    'nocase folded siteurl current appears after sitedebug' => ['site%', 'NOCASE', null, false, 3, 'currentRowid', 1],
    'nocase folded SiteURL next is rowid tie after siteurl' => ['site%', 'NOCASE', null, false, 3, 'nextRowid', 2],
    'nocase padded siteurl remains in range' => ['site%', 'NOCASE', null, false, 5, 'currentRowid', 3],
    'nocase row after site prefix exits range' => ['site%', 'NOCASE', null, false, 5, 'nextInRange', false],
    'nocase row after site prefix does not residual match' => ['site%', 'NOCASE', null, false, 5, 'nextResidualMatch', false],
    'nocase comparison to lower is zero for first admin row' => ['site%', 'NOCASE', null, false, 0, 'comparisonToLower', 1],
    'nocase comparison to upper is negative for first admin row' => ['site%', 'NOCASE', null, false, 0, 'comparisonToUpper', -1],
    'default LIKE rejects binary index range' => ['site%', 'BINARY', null, false, 0, 'rejectedReason', 'default_like_requires_nocase_index'],
    'default LIKE binary range is absent' => ['site%', 'BINARY', null, false, 0, 'range', null],
    'default LIKE binary current is not range usable' => ['site%', 'BINARY', null, false, 0, 'inRange', false],
    'case-sensitive binary lowercase plugin literal percent starts range' => ['plugin\_100\%%', 'BINARY', '\\', true, 0, 'currentRowid', 7],
    'case-sensitive binary lowercase plugin residual matches' => ['plugin\_100\%%', 'BINARY', '\\', true, 0, 'residualMatch', true],
    'case-sensitive binary beta row remains literal-prefix match' => ['plugin\_100\%%', 'BINARY', '\\', true, 1, 'currentRowid', 10],
    'case-sensitive binary wildcard x row exits literal-percent range' => ['plugin\_100\%%', 'BINARY', '\\', true, 2, 'inRange', false],
    'case-sensitive binary wildcard x row fails residual' => ['plugin\_100\%%', 'BINARY', '\\', true, 2, 'residualMatch', false],
    'case-sensitive binary next 200 row exits upper bound' => ['plugin\_100\%%', 'BINARY', '\\', true, 2, 'nextInRange', false],
    'nocase escaped plugin range includes lowercase rowid tie first' => ['plugin\_100\%%', 'NOCASE', '\\', false, 0, 'currentRowid', 7],
    'nocase escaped plugin lowercase residual matches' => ['plugin\_100\%%', 'NOCASE', '\\', false, 0, 'residualMatch', true],
    'nocase escaped plugin rowid tie keeps uppercase next' => ['plugin\_100\%%', 'NOCASE', '\\', false, 0, 'nextRowid', 8],
    'nocase escaped plugin x row remains residual false' => ['plugin\_100\%%', 'NOCASE', '\\', false, 3, 'residualMatch', false],
    'case-sensitive unicode binary range begins lowercase byte order' => ['é%', 'BINARY', null, true, 0, 'currentRowid', 12],
    'case-sensitive unicode lowercase row is in range' => ['é%', 'BINARY', null, true, 0, 'inRange', true],
    'case-sensitive unicode lowercase row residual matches' => ['é%', 'BINARY', null, true, 0, 'residualMatch', true],
    'case-sensitive unicode row after lowercase is eof' => ['é%', 'BINARY', null, true, 0, 'nextInRange', null],
    'default nocase unicode prefix is rejected for range' => ['é%', 'NOCASE', null, false, 0, 'rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'default nocase unicode prefix has no usable range' => ['é%', 'NOCASE', null, false, 0, 'inRange', false],
    'eof reports null current rowid after advancing beyond rows' => ['site%', 'NOCASE', null, false, 20, 'currentRowid', null],
    'eof reports true after advancing beyond rows' => ['site%', 'NOCASE', null, false, 20, 'eof', true],
    'eof keeps range metadata for diagnostics' => ['site%', 'NOCASE', null, false, 20, 'range.lowerInclusive', 'site'],
];

foreach ($planCases as $name => [$pattern, $collation, $escape, $caseSensitive, $advance, $path, $expected]) {
    $tests['like current next68 plan ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $collation, $escape, $caseSensitive, $advance, $path, $expected): void {
        $cursor = $makeCursor($pattern, $collation, $escape, $caseSensitive);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $value = $cursor->currentNextPlan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }

        $t->same($expected, $value);
    };
}

$matchCases = [
    'nocase site prefix returns folded current rows in index order' => ['site%', 'NOCASE', null, false, [4, 5, 6, 1, 2, 3]],
    'binary site prefix rejected range returns no rows under default like' => ['site%', 'BINARY', null, false, []],
    'case-sensitive binary site prefix returns lowercase site rows only' => ['site%', 'BINARY', null, true, [4, 6, 1, 3]],
    'nocase escaped plugin literal percent returns case folded rows' => ['plugin\_100\%%', 'NOCASE', '\\', false, [7, 8, 10]],
    'case-sensitive escaped plugin literal percent returns lowercase rows' => ['plugin\_100\%%', 'BINARY', '\\', true, [7, 10]],
    'case-sensitive binary plugin wildcard range filters residual x row' => ['plugin\_100%', 'BINARY', '\\', true, [7, 10, 9]],
    'case-sensitive unicode prefix returns lowercase unicode row only' => ['é%', 'BINARY', null, true, [12]],
    'default nocase unicode prefix returns no indexed rows without safe range' => ['é%', 'NOCASE', null, false, []],
    'terminal exact siteurl prefix range keeps exact folded names' => ['siteurl', 'NOCASE', null, false, [1, 2]],
    'padded exact siteurl does not match terminal exact pattern' => ['siteurl', 'NOCASE', null, false, [1, 2]],
    'percent-only pattern has no fixed prefix and no cursor rows' => ['%', 'NOCASE', null, false, []],
    'underscore-leading pattern has no fixed prefix and no cursor rows' => ['_ite%', 'NOCASE', null, false, []],
];

foreach ($matchCases as $name => [$pattern, $collation, $escape, $caseSensitive, $expectedRowids]) {
    $tests['like current next68 matched rows ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $collation, $escape, $caseSensitive, $expectedRowids): void {
        $cursor = $makeCursor($pattern, $collation, $escape, $caseSensitive);
        $t->same($expectedRowids, array_column($cursor->matchedRows(), 'rowid'));
    };
}

$tests['like current next68 matched rows preserve payload columns'] = static function (TestRunner $t) use ($makeCursor): void {
    $rows = $makeCursor('site%', 'NOCASE')->matchedRows();
    $t->same('site_admin_email', $rows[0]['payload']['option_name']);
    $t->same('yes', $rows[0]['payload']['autoload']);
};

$tests['like current next68 matched rows expose source positions'] = static function (TestRunner $t) use ($makeCursor): void {
    $rows = $makeCursor('plugin\_100\%%', 'NOCASE', '\\')->matchedRows();
    $t->same([0, 1, 2], array_column($rows, 'position'));
};

$tests['like current next68 rejects malformed entry payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor([['key' => 'siteurl', 'rowid' => 1]], 'site%'));
};

$tests['like current next68 rejects non text key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor([['key' => 10, 'rowid' => 1, 'payload' => []]], 'site%'));
};

$tests['like current next68 rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor([['key' => 'siteurl', 'rowid' => '1', 'payload' => []]], 'site%'));
};

$tests['like current next68 rejects unsupported collation'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor($entries, 'site%', 'WP_LOCALE'));
};

$tests['like current next68 rejects malformed escape'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor($entries, 'site%', 'NOCASE', 'xx'));
};

return $tests;
