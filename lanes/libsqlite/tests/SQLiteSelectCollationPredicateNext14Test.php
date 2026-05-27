<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'SiteURL', 'option_value' => 'https://network.test', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => "home   ", 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'HOME', 'option_value' => 'https://admin.test', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_Å', 'option_value' => 'latin-capital', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_å', 'option_value' => 'latin-small', 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'transient_timeout', 'option_value' => '123', 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => null, 'option_value' => 'orphan', 'autoload' => 'no'],
];

$database = ['wp_options' => $options];

$cases = [
    'nocase equality keeps ASCII case variants' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'SITEURL' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'right operand nocase equality is honored' => [
        "SELECT option_id FROM wp_options WHERE option_name = 'SITEURL' COLLATE NOCASE ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'binary equality remains case sensitive' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY = 'SITEURL'",
        [],
    ],
    'nocase inequality removes ASCII case variants' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE != 'SITEURL' ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'is not with nocase removes ASCII case variants' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IS NOT 'SITEURL' ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7], ['option_id' => 8]],
    ],
    'is distinct from with nocase removes ASCII case variants' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IS DISTINCT FROM 'SITEURL' ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7], ['option_id' => 8]],
    ],
    'is not distinct from with nocase keeps ASCII case variants' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IS NOT DISTINCT FROM 'SITEURL' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'rtrim equality ignores trailing spaces' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM = 'home'",
        [['option_id' => 3]],
    ],
    'binary equality sees trailing spaces' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY = 'home'",
        [],
    ],
    'right operand rtrim equality is honored' => [
        "SELECT option_id FROM wp_options WHERE option_name = 'home' COLLATE RTRIM",
        [['option_id' => 3]],
    ],
    'nocase less than uses ASCII folding' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE < 'plugin' ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 4]],
    ],
    'nocase greater than uses ASCII folding' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE > 'transient' ORDER BY option_id",
        [['option_id' => 7]],
    ],
    'nocase less-or-equal includes folded home' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE <= 'home' ORDER BY option_id",
        [['option_id' => 4]],
    ],
    'rtrim less-or-equal includes padded home' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM <= 'home' ORDER BY option_id",
        [['option_id' => 2], ['option_id' => 3], ['option_id' => 4]],
    ],
    'nocase between keeps folded home range' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE BETWEEN 'home' AND 'siteurl' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6]],
    ],
    'rtrim between includes padded home lower bound' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM BETWEEN 'home' AND 'home' ORDER BY option_id",
        [['option_id' => 3]],
    ],
    'not between with nocase excludes folded range' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE NOT BETWEEN 'home' AND 'siteurl' ORDER BY option_id",
        [['option_id' => 7]],
    ],
    'in list with nocase keeps folded names' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IN ('SITEURL', 'HOME') ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 4]],
    ],
    'right operand nocase in list is honored' => [
        "SELECT option_id FROM wp_options WHERE option_name IN ('SITEURL' COLLATE NOCASE, 'HOME' COLLATE NOCASE) ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 4]],
    ],
    'binary in list keeps exact case only' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY IN ('SITEURL', 'HOME') ORDER BY option_id",
        [['option_id' => 4]],
    ],
    'not in list with nocase removes folded names' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE NOT IN ('SITEURL', 'HOME') ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'not in list with null keeps SQL unknown filtering' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE NOT IN ('SITEURL', NULL) ORDER BY option_id",
        [],
    ],
    'nocase remains ASCII only for latin supplement' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'plugin_Å' ORDER BY option_id",
        [['option_id' => 5]],
    ],
    'binary separates latin supplement case' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY = 'plugin_å' ORDER BY option_id",
        [['option_id' => 6]],
    ],
    'rtrim in list ignores candidate padding' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM IN ('home', 'siteurl') ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 3]],
    ],
    'collated function expression compares folded text' => [
        "SELECT option_id FROM wp_options WHERE upper(option_name) COLLATE NOCASE = 'SITEURL' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'collated concatenation expression compares folded text' => [
        "SELECT option_id FROM wp_options WHERE (option_name || '') COLLATE NOCASE = 'HOME' ORDER BY option_id",
        [['option_id' => 4]],
    ],
    'collated cast expression compares folded text' => [
        "SELECT option_id FROM wp_options WHERE CAST(option_name AS TEXT) COLLATE NOCASE = 'SITEURL' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'and predicate preserves collated comparison' => [
        "SELECT option_id FROM wp_options WHERE autoload = 'yes' AND option_name COLLATE NOCASE = 'SITEURL'",
        [['option_id' => 1]],
    ],
    'or predicate preserves collated comparison' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'SITEURL' OR option_name COLLATE RTRIM = 'home' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 3]],
    ],
    'having predicate preserves collated comparison' => [
        "SELECT autoload, count(option_id) AS total FROM wp_options GROUP BY autoload HAVING autoload COLLATE NOCASE = 'YES'",
        [['autoload' => 'yes', 'total' => 3]],
    ],
    'join on predicate preserves collated comparison' => [
        "SELECT wp_options.option_id FROM wp_options JOIN aliases ON wp_options.option_name COLLATE NOCASE = aliases.name ORDER BY wp_options.option_id",
        [['wp_options.option_id' => 1], ['wp_options.option_id' => 2], ['wp_options.option_id' => 4]],
    ],
    'left join on predicate preserves collated comparison' => [
        "SELECT wp_options.option_id, aliases.label FROM wp_options LEFT JOIN aliases ON wp_options.option_name COLLATE NOCASE = aliases.name WHERE wp_options.option_id IN (1, 3) ORDER BY wp_options.option_id",
        [['wp_options.option_id' => 1, 'aliases.label' => 'site'], ['wp_options.option_id' => 3, 'aliases.label' => null]],
    ],
    'row-value equality uses collated element' => [
        "SELECT option_id FROM wp_options WHERE (autoload, option_name COLLATE NOCASE) = ('yes', 'SITEURL')",
        [['option_id' => 1]],
    ],
    'row-value in list uses collated element' => [
        "SELECT option_id FROM wp_options WHERE (autoload, option_name COLLATE NOCASE) IN (('yes', 'SITEURL'), ('no', 'HOME')) ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 4]],
    ],
    'row-value not in list uses collated element' => [
        "SELECT option_id FROM wp_options WHERE (autoload, option_name COLLATE NOCASE) NOT IN (('yes', 'SITEURL'), ('no', 'HOME')) ORDER BY option_id",
        [['option_id' => 2], ['option_id' => 3], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'null left operand still filters ordinary comparison' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'ORPHAN'",
        [],
    ],
    'null left operand is not distinct under collated comparison' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IS DISTINCT FROM 'ORPHAN'",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7], ['option_id' => 8]],
    ],
    'collated comparison with numeric storage keeps storage rank' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE > 100 ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'collated comparison with blob storage keeps storage rank' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE < X'4142' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'projected collated predicate keeps source value' => [
        "SELECT option_name AS name FROM wp_options WHERE option_name COLLATE RTRIM = 'home'",
        [['name' => 'home   ']],
    ],
    'collated predicate after comma join preserves source aliases' => [
        "SELECT o.option_id, a.label FROM wp_options AS o, aliases AS a WHERE o.option_name COLLATE NOCASE = a.name ORDER BY o.option_id",
        [['o.option_id' => 1, 'a.label' => 'site'], ['o.option_id' => 2, 'a.label' => 'site'], ['o.option_id' => 4, 'a.label' => 'home']],
    ],
    'collated predicate with limit keeps first folded match' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'SITEURL' ORDER BY option_id LIMIT 1",
        [['option_id' => 1]],
    ],
    'collated predicate with offset keeps second folded match' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE = 'SITEURL' ORDER BY option_id LIMIT 1 OFFSET 1",
        [['option_id' => 2]],
    ],
    'collated predicate with distinct removes folded duplicates by output value only' => [
        "SELECT DISTINCT autoload FROM wp_options WHERE option_name COLLATE NOCASE IN ('SITEURL', 'HOME') ORDER BY autoload",
        [['autoload' => 'no'], ['autoload' => 'yes']],
    ],
    'unsupported predicate collation is rejected by parser' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE WPNATURAL = 'siteurl'",
        InvalidArgumentException::class,
    ],
    'cte predicate preserves collated comparison' => [
        "WITH wanted(name) AS (VALUES ('SITEURL')) SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IN (SELECT name FROM wanted) ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'collated not-equals with right operand keeps nonmatching names' => [
        "SELECT option_id FROM wp_options WHERE option_name != 'SITEURL' COLLATE NOCASE ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'collated greater-or-equal includes folded siteurl' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE >= 'siteurl' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 7]],
    ],
    'rtrim not equal removes padded home' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM <> 'home' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
    'rtrim is distinct from removes padded home' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM IS DISTINCT FROM 'home' ORDER BY option_id",
        [['option_id' => 1], ['option_id' => 2], ['option_id' => 4], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7], ['option_id' => 8]],
    ],
    'rtrim is not distinct from keeps padded home' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE RTRIM IS NOT DISTINCT FROM 'home'",
        [['option_id' => 3]],
    ],
    'binary right operand keeps exact siteurl' => [
        "SELECT option_id FROM wp_options WHERE option_name = 'siteurl' COLLATE BINARY",
        [['option_id' => 1]],
    ],
    'nocase in list preserves null unknown for unmatched null row' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE IN ('missing', NULL) ORDER BY option_id",
        [],
    ],
    'nocase not in list with no null keeps unmatched text rows' => [
        "SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE NOT IN ('siteurl', 'home') ORDER BY option_id",
        [['option_id' => 3], ['option_id' => 5], ['option_id' => 6], ['option_id' => 7]],
    ],
];

$aliases = [
    ['name' => 'SITEURL', 'label' => 'site'],
    ['name' => 'home', 'label' => 'home'],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['select collation predicate next14 ' . $name] = static function (TestRunner $t) use ($sql, $expected, $database, $aliases): void {
        $tables = $database + ['aliases' => $aliases];
        if (is_string($expected) && class_exists($expected)) {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, $tables));
            return;
        }

        $t->same($expected, SQLiteSelectSql::execute($sql, $tables));
    };
}

return $tests;
