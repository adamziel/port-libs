<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLikeCollationPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$planCases = [
    'default nocase site prefix uses nocase index range' => ['site%', 'NOCASE', null, false, true, 'site', 'sitf', null],
    'default binary site prefix rejected' => ['site%', 'BINARY', null, false, false, null, null, 'default_like_requires_nocase_index'],
    'default rtrim site prefix rejected' => ['site%', 'RTRIM', null, false, false, null, null, 'default_like_requires_nocase_index'],
    'case sensitive binary site prefix uses binary range' => ['site%', 'BINARY', null, true, true, 'site', 'sitf', null],
    'case sensitive nocase site prefix rejected' => ['site%', 'NOCASE', null, true, false, null, null, 'case_sensitive_like_requires_binary_index'],
    'case sensitive rtrim site prefix rejected' => ['site%', 'RTRIM', null, true, false, null, null, 'case_sensitive_like_requires_binary_index'],
    'default nocase escaped percent keeps literal prefix' => ['plugin\_100\%%', 'NOCASE', '\\', false, true, 'plugin_100%', 'plugin_100&', null],
    'case sensitive binary escaped underscore keeps literal prefix' => ['plugin\_core%', 'BINARY', '\\', true, true, 'plugin_core', 'plugin_corf', null],
    'default nocase no fixed prefix rejected' => ['%site', 'NOCASE', null, false, false, null, null, 'no_fixed_prefix'],
    'case sensitive binary no fixed prefix rejected' => ['_site%', 'BINARY', null, true, false, null, null, 'no_fixed_prefix'],
    'default nocase unicode prefix rejected for range' => ['é%', 'NOCASE', null, false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary unicode prefix keeps byte range' => ['é%', 'BINARY', null, true, true, 'é', 'ê', null],
    'default nocase ascii prefix before unicode wildcard remains usable' => ['plugin_é%', 'NOCASE', null, false, true, 'plugin', 'plugio', null],
    'case sensitive binary ascii prefix before unicode wildcard remains usable' => ['plugin_é%', 'BINARY', null, true, true, 'plugin', 'plugio', null],
    'default nocase escaped unicode literal rejected for range' => ['\é%', 'NOCASE', '\\', false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary escaped unicode literal keeps byte range' => ['\é%', 'BINARY', '\\', true, true, 'é', 'ê', null],
    'default nocase terminal literal prefix without wildcard usable' => ['autoload', 'NOCASE', null, false, true, 'autoload', 'autoloae', null],
    'case sensitive binary terminal literal prefix without wildcard usable' => ['autoload', 'BINARY', null, true, true, 'autoload', 'autoloae', null],
    'default nocase max byte prefix has no upper bound' => ["\xff%", 'NOCASE', null, false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary max byte prefix has no upper bound' => ["\xff%", 'BINARY', null, true, true, "\xff", null, null],
    'default nocase ascii after malformed byte rejected for range' => ["plugin_\xc3%", 'NOCASE', null, false, true, 'plugin', 'plugio', null],
    'case sensitive binary ascii after malformed byte keeps ascii prefix' => ["plugin_\xc3%", 'BINARY', null, true, true, 'plugin', 'plugio', null],
];

foreach ($planCases as $name => [$pattern, $collation, $escape, $caseSensitive, $usable, $lower, $upper, $reason]) {
    $tests['like collation current next65 plan ' . $name] = static function (TestRunner $t) use ($pattern, $collation, $escape, $caseSensitive, $usable, $lower, $upper, $reason): void {
        $plan = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitive);
        $t->same($collation, $plan['collation']);
        $t->same($caseSensitive, $plan['caseSensitiveLike']);
        $t->same($usable, $plan['indexUsable']);
        $t->same($reason, $plan['rejectedReason']);
        if ($usable) {
            $t->same($lower, $plan['range']['lowerInclusive']);
            $t->same($upper, $plan['range']['upperBound']);
        } else {
            $t->same(null, $plan['range']);
        }
    };
}

$rows = [
    ['option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'siteurl ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_100%_enabled', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'Plugin_100%_Enabled', 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'é_plugin', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'É_plugin', 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => null, 'autoload' => 'no'],
];

$filterCases = [
    'default nocase matches ascii case variants' => ['site%', 'NOCASE', null, false, [1, 2, 3]],
    'case sensitive binary keeps ascii case distinct' => ['site%', 'BINARY', null, true, [2, 3]],
    'default nocase ignores declared binary for matcher semantics' => ['site%', 'BINARY', null, false, [1, 2, 3]],
    'default nocase ignores declared rtrim for matcher semantics' => ['siteurl', 'RTRIM', null, false, [1, 2]],
    'case sensitive binary does not rtrim trailing spaces' => ['siteurl', 'BINARY', null, true, [2]],
    'escaped percent matches literal percent under nocase' => ['plugin\_100\%%', 'NOCASE', '\\', false, [4, 5]],
    'escaped percent is case sensitive when requested' => ['plugin\_100\%%', 'BINARY', '\\', true, [4]],
    'unicode remains case sensitive under default LIKE' => ['é%', 'NOCASE', null, false, [6]],
    'uppercase unicode does not fold to lowercase pattern' => ['é%', 'BINARY', null, false, [6]],
    'uppercase unicode matches exact uppercase pattern' => ['É%', 'NOCASE', null, false, [7]],
    'percent pattern skips null rows' => ['%', 'NOCASE', null, false, [1, 2, 3, 4, 5, 6, 7]],
    'escaped underscore remains literal' => ['plugin\_100%', 'NOCASE', '\\', false, [4, 5]],
];

foreach ($filterCases as $name => [$pattern, $collation, $escape, $caseSensitive, $expected]) {
    $tests['like collation current next65 filter ' . $name] = static function (TestRunner $t) use ($rows, $pattern, $collation, $escape, $caseSensitive, $expected): void {
        $matched = SQLiteLikeCollationPlan::filterRows($rows, 'option_name', $pattern, $collation, $escape, $caseSensitive);
        $t->same($expected, array_column($matched, 'option_id'));
    };
}

$sqlCases = [
    'select sql like left collate nocase still uses LIKE ascii folding' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE LIKE 'site%' ORDER BY option_id", [1, 2, 3]],
    'select sql like left collate binary still uses LIKE ascii folding' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY LIKE 'site%' ORDER BY option_id", [1, 2, 3]],
    'select sql like left collate rtrim does not trim equality-like pattern' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM LIKE 'siteurl' ORDER BY option_id", [1, 2]],
    'select sql like right collate nocase still keeps unicode case distinct' => ["SELECT option_id FROM wp_options WHERE option_name LIKE 'é%' COLLATE NOCASE ORDER BY option_id", [6]],
    'select sql not like left collate excludes ascii folded matches' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY NOT LIKE 'site%' ORDER BY option_id", [4, 5, 6, 7]],
    'select sql escaped like with collated left keeps literal wildcards' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE LIKE 'plugin\\_100\\%%' ESCAPE '\\' ORDER BY option_id", [4, 5]],
    'select sql glob left collate nocase remains case sensitive' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE GLOB 'site*' ORDER BY option_id", [2, 3]],
    'select sql not glob left collate nocase excludes only exact glob matches' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE NOT GLOB 'site*' ORDER BY option_id", [1, 4, 5, 6, 7]],
    'select sql glob unicode class with collated left keeps unicode range' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY GLOB '[À-ÿ]*' ORDER BY option_id", [6, 7]],
    'select sql glob uppercase unicode matches same range' => ["SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE GLOB '[À-ÿ]*' ORDER BY option_id", [6, 7]],
    'select sql like collate after expression keeps hidden expression behavior' => ["SELECT option_id FROM wp_options WHERE (option_name || '') COLLATE NOCASE LIKE 'plugin\\_100\\%%' ESCAPE '\\' ORDER BY option_id", [4, 5]],
    'select sql like collated right expression keeps matcher behavior' => ["SELECT option_id FROM wp_options WHERE option_name LIKE ('SITE%' COLLATE RTRIM) ORDER BY option_id", [1, 2, 3]],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['like collation current next65 sql ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $t->same($expected, array_column($result, 'option_id'));
    };
}

$tests['like collation current next65 rejects unsupported collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::plan('site%', 'WP_LOCALE'));
};

$tests['like collation current next65 rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::filterRows([['name' => 'siteurl']], 'option_name', 'site%', 'NOCASE'));
};

$tests['like collation current next65 rejects non text filter value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::filterRows([['option_name' => 12]], 'option_name', 'site%', 'NOCASE'));
};

$tests['like collation current next65 rejects multi character escape through planner'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::plan('site%', 'NOCASE', 'xx'));
};

return $tests;
