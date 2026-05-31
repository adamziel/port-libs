<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectCompound;
use PortLibs\LibSqlite\SQLiteSelectSql;

$left = [
    ['name' => 'siteurl', 'kind' => 'single'],
    ['name' => 'HOME ', 'kind' => 'single'],
    ['name' => 'blogname', 'kind' => 'single'],
    ['name' => null, 'kind' => 'single'],
];
$right = [
    ['name' => 'SiteURL', 'kind' => 'network'],
    ['name' => 'home', 'kind' => 'network'],
    ['name' => 'blogname ', 'kind' => 'network'],
    ['name' => null, 'kind' => 'network'],
];
$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'HOME ', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => null, 'autoload' => 'yes'],
];
$network = [
    ['option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'blogname ', 'autoload' => 'yes'],
    ['option_id' => 13, 'option_name' => null, 'autoload' => 'yes'],
];

return [
    'unions nocase duplicate text with latest duplicate row retained' => static function (TestRunner $t) use ($left): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'UNION', ['name' => 'NOCASE']);
        $t->same(['SiteURL'], array_column($rows, 'name'));
    },
    'unions binary text without folding case' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'UNION', ['name' => 'BINARY']);
        $t->same(['siteurl', 'SiteURL'], array_column($rows, 'name'));
    },
    'unions rtrim duplicate text with latest duplicate row retained' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'home']], [['name' => 'home  ']], 'UNION', ['name' => 'RTRIM']);
        $t->same(['home  '], array_column($rows, 'name'));
    },
    'unions rtrim still keeps different case distinct' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'HOME ']], [['name' => 'home']], 'UNION', ['name' => 'RTRIM']);
        $t->same(['HOME ', 'home'], array_column($rows, 'name'));
    },
    'intersects nocase duplicate text' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'INTERSECT', ['name' => 'NOCASE']);
        $t->same(['siteurl'], array_column($rows, 'name'));
    },
    'intersects binary text by exact bytes' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'INTERSECT', ['name' => 'BINARY']);
        $t->same([], $rows);
    },
    'except removes nocase duplicate text' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl'], ['name' => 'home']], [['name' => 'SiteURL']], 'EXCEPT', ['name' => 'NOCASE']);
        $t->same(['home'], array_column($rows, 'name'));
    },
    'except keeps binary case variant' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'EXCEPT', ['name' => 'BINARY']);
        $t->same(['siteurl'], array_column($rows, 'name'));
    },
    'collated union keeps null duplicate semantics' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => null]], [['name' => null]], 'UNION', ['name' => 'NOCASE']);
        $t->same([null], array_column($rows, 'name'));
    },
    'collated union all does not remove duplicates' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl']], [['name' => 'SiteURL']], 'UNION ALL', ['name' => 'NOCASE']);
        $t->same(['siteurl', 'SiteURL'], array_column($rows, 'name'));
    },
    'collated union uses selected column only' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine([['name' => 'siteurl', 'kind' => 'single']], [['name' => 'SiteURL', 'kind' => 'network']], 'UNION', ['name' => 'NOCASE']);
        $t->same(['single', 'network'], array_column($rows, 'kind'));
    },
    'compound execute orders collated union after duplicate removal' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::execute([['name' => 'siteurl'], ['name' => 'home']], [['name' => 'SiteURL'], ['name' => 'blogname']], 'UNION', [['column' => 'name', 'collation' => 'NOCASE']], null, 0);
        $t->same(['blogname', 'home', 'siteurl', 'SiteURL'], array_column($rows, 'name'));
    },
    'select sql union honors projected nocase collation' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id = 1 UNION SELECT option_name AS name FROM network_options WHERE option_id = 10", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['SiteURL'], array_column($rows, 'name'));
    },
    'select sql union honors projected rtrim collation' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE RTRIM AS name FROM wp_options WHERE option_id = 3 UNION SELECT option_name AS name FROM network_options WHERE option_id = 12", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['blogname '], array_column($rows, 'name'));
    },
    'select sql intersect honors projected nocase collation' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id = 1 INTERSECT SELECT option_name AS name FROM network_options WHERE option_id = 10", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['siteurl'], array_column($rows, 'name'));
    },
    'select sql except honors projected nocase collation' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id IN (1, 2) EXCEPT SELECT option_name AS name FROM network_options WHERE option_id = 10 ORDER BY name COLLATE NOCASE", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['HOME '], array_column($rows, 'name'));
    },
    'select sql compound order by collated alias after set comparison' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id IN (1, 2, 3) UNION SELECT option_name AS name FROM network_options WHERE option_id IN (10, 11, 12) ORDER BY name COLLATE NOCASE LIMIT 4", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['blogname', 'blogname ', 'home', 'HOME '], array_column($rows, 'name'));
    },
    'select sql compound order by nulls last with projected collation' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options UNION SELECT option_name AS name FROM network_options ORDER BY name COLLATE NOCASE NULLS LAST LIMIT 5", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['blogname', 'blogname ', 'home', 'HOME ', 'SiteURL'], array_column($rows, 'name'));
    },
    'select sql compound comma limit follows collated duplicate removal' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options UNION SELECT option_name AS name FROM network_options ORDER BY name COLLATE NOCASE NULLS LAST LIMIT 1, 2", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['blogname ', 'home'], array_column($rows, 'name'));
    },
    'select sql compound offset follows collated duplicate removal' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options UNION SELECT option_name AS name FROM network_options ORDER BY name COLLATE NOCASE NULLS LAST LIMIT 2 OFFSET 2", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['home', 'HOME '], array_column($rows, 'name'));
    },
    'left arm explicit binary collation remains binary even when right arm is nocase' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE BINARY AS name FROM wp_options WHERE option_id = 1 UNION SELECT option_name COLLATE NOCASE AS name FROM network_options WHERE option_id = 10 ORDER BY name", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['SiteURL', 'siteurl'], array_column($rows, 'name'));
    },
    'left arm nocase collation folds chained union comparisons with latest duplicate row' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id = 1 UNION ALL SELECT option_name AS name FROM network_options WHERE option_id = 10 UNION SELECT 'SITEURL' AS name ORDER BY name COLLATE NOCASE", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['SITEURL'], array_column($rows, 'name'));
    },
    'projected collate expression preserves result column name' => static function (TestRunner $t) use ($options, $network): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name COLLATE NOCASE AS name FROM wp_options WHERE option_id = 1 UNION SELECT option_name AS name FROM network_options WHERE option_id = 10", ['wp_options' => $options, 'network_options' => $network]);
        $t->same(['name'], array_keys($rows[0]));
    },
    'compound plan records final order by collation separately' => static function (TestRunner $t) use ($options, $network): void {
        $plan = SQLiteSelectSql::plan("SELECT option_name COLLATE NOCASE AS name FROM wp_options UNION SELECT option_name AS name FROM network_options ORDER BY name COLLATE RTRIM NULLS LAST LIMIT 2", ['wp_options' => $options, 'network_options' => $network]);
        $t->same('RTRIM', $plan['compound']['orderBy'][0]['collation']);
        $t->same('LAST', $plan['compound']['orderBy'][0]['nulls']);
    },
    'rejects unsupported projected collate before compound execution' => static function (TestRunner $t) use ($options, $network): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT option_name COLLATE WPNATURAL AS name FROM wp_options UNION SELECT option_name AS name FROM network_options", ['wp_options' => $options, 'network_options' => $network]));
    },
    'rejects unsupported compound set collation metadata' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCompound::combine([['name' => 'a']], [['name' => 'A']], 'UNION', ['name' => 'WPNATURAL']));
    },
];
