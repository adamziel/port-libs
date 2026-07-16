<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLikeCollationPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$planCases = [
    'default nocase base prefix uses nocase index range' => ['base%', 'NOCASE', null, false, true, 'base', 'basf', null],
    'default binary base prefix rejected' => ['base%', 'BINARY', null, false, false, null, null, 'default_like_requires_nocase_index'],
    'default rtrim base prefix rejected' => ['base%', 'RTRIM', null, false, false, null, null, 'default_like_requires_nocase_index'],
    'case sensitive binary base prefix uses binary range' => ['base%', 'BINARY', null, true, true, 'base', 'basf', null],
    'case sensitive nocase base prefix rejected' => ['base%', 'NOCASE', null, true, false, null, null, 'case_sensitive_like_requires_binary_index'],
    'case sensitive rtrim base prefix rejected' => ['base%', 'RTRIM', null, true, false, null, null, 'case_sensitive_like_requires_binary_index'],
    'default nocase escaped percent keeps literal prefix' => ['module\_100\%%', 'NOCASE', '\\', false, true, 'module_100%', 'module_100&', null],
    'case sensitive binary escaped underscore keeps literal prefix' => ['module\_core%', 'BINARY', '\\', true, true, 'module_core', 'module_corf', null],
    'default nocase no fixed prefix rejected' => ['%base', 'NOCASE', null, false, false, null, null, 'no_fixed_prefix'],
    'case sensitive binary no fixed prefix rejected' => ['_base%', 'BINARY', null, true, false, null, null, 'no_fixed_prefix'],
    'default nocase unicode prefix rejected for range' => ['é%', 'NOCASE', null, false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary unicode prefix keeps byte range' => ['é%', 'BINARY', null, true, true, 'é', 'ê', null],
    'default nocase ascii prefix before unicode wildcard remains usable' => ['module_é%', 'NOCASE', null, false, true, 'module', 'modulf', null],
    'case sensitive binary ascii prefix before unicode wildcard remains usable' => ['module_é%', 'BINARY', null, true, true, 'module', 'modulf', null],
    'default nocase escaped unicode literal rejected for range' => ['\é%', 'NOCASE', '\\', false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary escaped unicode literal keeps byte range' => ['\é%', 'BINARY', '\\', true, true, 'é', 'ê', null],
    'default nocase terminal literal prefix without wildcard usable' => ['loadpolicy', 'NOCASE', null, false, true, 'loadpolicy', 'loadpolicz', null],
    'case sensitive binary terminal literal prefix without wildcard usable' => ['loadpolicy', 'BINARY', null, true, true, 'loadpolicy', 'loadpolicz', null],
    'default nocase max byte prefix has no upper bound' => ["\xff%", 'NOCASE', null, false, false, null, null, 'nocase_like_prefix_must_be_ascii_for_range'],
    'case sensitive binary max byte prefix has no upper bound' => ["\xff%", 'BINARY', null, true, true, "\xff", null, null],
    'default nocase ascii after malformed byte rejected for range' => ["module_\xc3%", 'NOCASE', null, false, true, 'module', 'modulf', null],
    'case sensitive binary ascii after malformed byte keeps ascii prefix' => ["module_\xc3%", 'BINARY', null, true, true, 'module', 'modulf', null],
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
    ['setting_id' => 1, 'key_name' => 'BaseURL', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'baseurl', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'baseurl ', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'module_100%_enabled', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'Module_100%_Enabled', 'load_policy' => 'no'],
    ['setting_id' => 6, 'key_name' => 'é_module', 'load_policy' => 'yes'],
    ['setting_id' => 7, 'key_name' => 'É_module', 'load_policy' => 'no'],
    ['setting_id' => 8, 'key_name' => null, 'load_policy' => 'no'],
];

$filterCases = [
    'default nocase matches ascii case variants' => ['base%', 'NOCASE', null, false, [1, 2, 3]],
    'case sensitive binary keeps ascii case distinct' => ['base%', 'BINARY', null, true, [2, 3]],
    'default nocase ignores declared binary for matcher semantics' => ['base%', 'BINARY', null, false, [1, 2, 3]],
    'default nocase ignores declared rtrim for matcher semantics' => ['baseurl', 'RTRIM', null, false, [1, 2]],
    'case sensitive binary does not rtrim trailing spaces' => ['baseurl', 'BINARY', null, true, [2]],
    'escaped percent matches literal percent under nocase' => ['module\_100\%%', 'NOCASE', '\\', false, [4, 5]],
    'escaped percent is case sensitive when requested' => ['module\_100\%%', 'BINARY', '\\', true, [4]],
    'unicode remains case sensitive under default LIKE' => ['é%', 'NOCASE', null, false, [6]],
    'uppercase unicode does not fold to lowercase pattern' => ['é%', 'BINARY', null, false, [6]],
    'uppercase unicode matches exact uppercase pattern' => ['É%', 'NOCASE', null, false, [7]],
    'percent pattern skips null rows' => ['%', 'NOCASE', null, false, [1, 2, 3, 4, 5, 6, 7]],
    'escaped underscore remains literal' => ['module\_100%', 'NOCASE', '\\', false, [4, 5]],
];

foreach ($filterCases as $name => [$pattern, $collation, $escape, $caseSensitive, $expected]) {
    $tests['like collation current next65 filter ' . $name] = static function (TestRunner $t) use ($rows, $pattern, $collation, $escape, $caseSensitive, $expected): void {
        $matched = SQLiteLikeCollationPlan::filterRows($rows, 'key_name', $pattern, $collation, $escape, $caseSensitive);
        $t->same($expected, array_column($matched, 'setting_id'));
    };
}

$sqlCases = [
    'select sql like left collate nocase still uses LIKE ascii folding' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE NOCASE LIKE 'base%' ORDER BY setting_id", [1, 2, 3]],
    'select sql like left collate binary still uses LIKE ascii folding' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE BINARY LIKE 'base%' ORDER BY setting_id", [1, 2, 3]],
    'select sql like left collate rtrim does not trim equality-like pattern' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE RTRIM LIKE 'baseurl' ORDER BY setting_id", [1, 2]],
    'select sql like right collate nocase still keeps unicode case distinct' => ["SELECT setting_id FROM app_settings WHERE key_name LIKE 'é%' COLLATE NOCASE ORDER BY setting_id", [6]],
    'select sql not like left collate excludes ascii folded matches' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE BINARY NOT LIKE 'base%' ORDER BY setting_id", [4, 5, 6, 7]],
    'select sql escaped like with collated left keeps literal wildcards' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE NOCASE LIKE 'module\\_100\\%%' ESCAPE '\\' ORDER BY setting_id", [4, 5]],
    'select sql glob left collate nocase remains case sensitive' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE NOCASE GLOB 'base*' ORDER BY setting_id", [2, 3]],
    'select sql not glob left collate nocase excludes only exact glob matches' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE NOCASE NOT GLOB 'base*' ORDER BY setting_id", [1, 4, 5, 6, 7]],
    'select sql glob unicode class with collated left keeps unicode range' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE BINARY GLOB '[À-ÿ]*' ORDER BY setting_id", [6, 7]],
    'select sql glob uppercase unicode matches same range' => ["SELECT setting_id FROM app_settings WHERE key_name COLLATE NOCASE GLOB '[À-ÿ]*' ORDER BY setting_id", [6, 7]],
    'select sql like collate after expression keeps hidden expression behavior' => ["SELECT setting_id FROM app_settings WHERE (key_name || '') COLLATE NOCASE LIKE 'module\\_100\\%%' ESCAPE '\\' ORDER BY setting_id", [4, 5]],
    'select sql like collated right expression keeps matcher behavior' => ["SELECT setting_id FROM app_settings WHERE key_name LIKE ('BASE%' COLLATE RTRIM) ORDER BY setting_id", [1, 2, 3]],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['like collation current next65 sql ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $result = SQLiteSelectSql::execute($sql, ['app_settings' => $rows]);
        $t->same($expected, array_column($result, 'setting_id'));
    };
}

$tests['like collation current next65 rejects unsupported collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::plan('base%', 'APP_LOCALE'));
};

$tests['like collation current next65 rejects missing filter column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::filterRows([['name' => 'baseurl']], 'key_name', 'base%', 'NOCASE'));
};

$tests['like collation current next65 rejects non text filter value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::filterRows([['key_name' => 12]], 'key_name', 'base%', 'NOCASE'));
};

$tests['like collation current next65 rejects multi character escape through planner'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLikeCollationPlan::plan('base%', 'NOCASE', 'xx'));
};

return $tests;
