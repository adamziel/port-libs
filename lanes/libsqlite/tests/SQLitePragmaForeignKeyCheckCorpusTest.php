<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheck;

$tables = [
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_type' => 'post'],
        ['rowid' => 2, 'ID' => 2, 'post_type' => 'page'],
    ],
    'wp_postmeta' => [
        ['rowid' => 10, 'meta_id' => 10, 'post_id' => 1, 'meta_key' => '_edit_lock'],
        ['rowid' => 11, 'meta_id' => 11, 'post_id' => 99, 'meta_key' => '_missing'],
        ['rowid' => 12, 'meta_id' => 12, 'post_id' => null, 'meta_key' => '_draft'],
        ['rowid' => 13, 'meta_id' => 13, 'post_id' => 2, 'meta_key' => '_thumbnail_id'],
    ],
    'wp_term_taxonomy' => [
        ['rowid' => 20, 'term_id' => 5, 'taxonomy' => 'category'],
        ['rowid' => 21, 'term_id' => 5, 'taxonomy' => 'post_tag'],
    ],
    'wp_term_relationships' => [
        ['rowid' => 30, 'object_id' => 1, 'term_id' => 5, 'taxonomy' => 'category'],
        ['rowid' => 31, 'object_id' => 9, 'term_id' => 5, 'taxonomy' => 'category'],
        ['rowid' => 32, 'object_id' => 2, 'term_id' => 7, 'taxonomy' => 'category'],
        ['rowid' => 33, 'object_id' => null, 'term_id' => 5, 'taxonomy' => 'category'],
        ['rowid' => 34, 'object_id' => 2, 'term_id' => 5, 'taxonomy' => null],
    ],
    'wp_commentmeta' => [
        ['meta_id' => 40, 'comment_id' => 404],
    ],
    'wp_comments' => [
        ['rowid' => 50, 'comment_ID' => 50],
    ],
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
    ['id' => 1, 'table' => 'wp_term_relationships', 'parent' => 'wp_posts', 'columns' => ['object_id' => 'ID']],
    ['id' => 2, 'table' => 'wp_term_relationships', 'parent' => 'wp_term_taxonomy', 'columns' => ['term_id' => 'term_id', 'taxonomy' => 'taxonomy']],
    ['id' => 3, 'table' => 'wp_commentmeta', 'parent' => 'wp_comments', 'columns' => ['comment_id' => 'comment_ID'], 'without_rowid' => true],
];

$all = static fn (): array => SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys);
$postmeta = static fn (): array => SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys, 'wp_postmeta');
$relationships = static fn (): array => SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys, 'wp_term_relationships');
$execute = static fn (string $sql): array => SQLitePragmaForeignKeyCheck::execute($sql, $tables, $foreignKeys);

$tests = [
    'foreign key check corpus reports four violating rows' => static function (TestRunner $t) use ($all): void {
        $t->same(4, count($all()));
    },
    'foreign key check corpus reports child table name' => static function (TestRunner $t) use ($all): void {
        $t->same('wp_postmeta', $all()[0]['table']);
    },
    'foreign key check corpus reports child rowid' => static function (TestRunner $t) use ($all): void {
        $t->same(11, $all()[0]['rowid']);
    },
    'foreign key check corpus reports parent table name' => static function (TestRunner $t) use ($all): void {
        $t->same('wp_posts', $all()[0]['parent']);
    },
    'foreign key check corpus reports fkid ordinal' => static function (TestRunner $t) use ($all): void {
        $t->same(0, $all()[0]['fkid']);
    },
    'foreign key check corpus skips valid postmeta parent' => static function (TestRunner $t) use ($all): void {
        $t->same(false, in_array(10, array_column($all(), 'rowid'), true));
    },
    'foreign key check corpus skips null child key' => static function (TestRunner $t) use ($all): void {
        $t->same(false, in_array(12, array_column($all(), 'rowid'), true));
    },
    'foreign key check corpus skips second valid postmeta parent' => static function (TestRunner $t) use ($all): void {
        $t->same(false, in_array(13, array_column($all(), 'rowid'), true));
    },
    'foreign key check corpus reports missing object parent' => static function (TestRunner $t) use ($all): void {
        $t->same(['table' => 'wp_term_relationships', 'rowid' => 31, 'parent' => 'wp_posts', 'fkid' => 1], $all()[1]);
    },
    'foreign key check corpus reports missing composite parent' => static function (TestRunner $t) use ($all): void {
        $t->same(['table' => 'wp_term_relationships', 'rowid' => 32, 'parent' => 'wp_term_taxonomy', 'fkid' => 2], $all()[2]);
    },
    'foreign key check corpus skips composite row with null first child key' => static function (TestRunner $t) use ($all): void {
        $t->same(false, in_array(33, array_column($all(), 'rowid'), true));
    },
    'foreign key check corpus skips composite row with null second child key' => static function (TestRunner $t) use ($all): void {
        $t->same(false, in_array(34, array_column($all(), 'rowid'), true));
    },
    'foreign key check corpus returns null rowid for without rowid child table' => static function (TestRunner $t) use ($all): void {
        $t->same(null, $all()[3]['rowid']);
    },
    'foreign key check corpus keeps without rowid child table name' => static function (TestRunner $t) use ($all): void {
        $t->same('wp_commentmeta', $all()[3]['table']);
    },
    'foreign key check corpus keeps without rowid parent table name' => static function (TestRunner $t) use ($all): void {
        $t->same('wp_comments', $all()[3]['parent']);
    },
    'foreign key check corpus keeps without rowid fkid' => static function (TestRunner $t) use ($all): void {
        $t->same(3, $all()[3]['fkid']);
    },
    'foreign key check corpus filters child table' => static function (TestRunner $t) use ($postmeta): void {
        $t->same(1, count($postmeta()));
    },
    'foreign key check corpus filtered child keeps violation rowid' => static function (TestRunner $t) use ($postmeta): void {
        $t->same(11, $postmeta()[0]['rowid']);
    },
    'foreign key check corpus filtered child keeps parent table' => static function (TestRunner $t) use ($postmeta): void {
        $t->same('wp_posts', $postmeta()[0]['parent']);
    },
    'foreign key check corpus filters relationship table violations' => static function (TestRunner $t) use ($relationships): void {
        $t->same([31, 32], array_column($relationships(), 'rowid'));
    },
    'foreign key check corpus filters relationship fkids' => static function (TestRunner $t) use ($relationships): void {
        $t->same([1, 2], array_column($relationships(), 'fkid'));
    },
    'foreign key check corpus missing target returns empty rows' => static function (TestRunner $t): void {
        $t->same([], SQLitePragmaForeignKeyCheck::check([], [], 'wp_missing'));
    },
    'foreign key check corpus treats missing parent table as violations' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLitePragmaForeignKeyCheck::check($tables, [['table' => 'wp_postmeta', 'parent' => 'wp_missing_posts', 'columns' => ['post_id' => 'ID']]]);
        $t->same([10, 11, 13], array_column($rows, 'rowid'));
    },
    'foreign key check corpus preserves declaration order' => static function (TestRunner $t) use ($all): void {
        $t->same([0, 1, 2, 3], array_column($all(), 'fkid'));
    },
    'foreign key check corpus execute bare pragma' => static function (TestRunner $t) use ($execute): void {
        $t->same('foreign_key_check', $execute('PRAGMA foreign_key_check')['pragma']);
    },
    'foreign key check corpus execute default schema' => static function (TestRunner $t) use ($execute): void {
        $t->same('main', $execute('PRAGMA foreign_key_check')['schema']);
    },
    'foreign key check corpus execute bare rows' => static function (TestRunner $t) use ($execute): void {
        $t->same(4, count($execute('PRAGMA foreign_key_check')['rows']));
    },
    'foreign key check corpus execute parenthesized target' => static function (TestRunner $t) use ($execute): void {
        $t->same([11], array_column($execute('PRAGMA foreign_key_check(wp_postmeta)')['rows'], 'rowid'));
    },
    'foreign key check corpus execute single quoted target' => static function (TestRunner $t) use ($execute): void {
        $t->same('wp_postmeta', $execute("PRAGMA foreign_key_check('wp_postmeta')")['target']);
    },
    'foreign key check corpus execute double quoted target' => static function (TestRunner $t) use ($execute): void {
        $t->same([31, 32], array_column($execute('PRAGMA foreign_key_check("wp_term_relationships")')['rows'], 'rowid'));
    },
    'foreign key check corpus execute bracket quoted target' => static function (TestRunner $t) use ($execute): void {
        $t->same('wp_postmeta', $execute('PRAGMA foreign_key_check([wp_postmeta])')['target']);
    },
    'foreign key check corpus execute backtick quoted target' => static function (TestRunner $t) use ($execute): void {
        $t->same('wp_postmeta', $execute('PRAGMA foreign_key_check(`wp_postmeta`)')['target']);
    },
    'foreign key check corpus execute schema qualified pragma' => static function (TestRunner $t) use ($execute): void {
        $t->same('temp', $execute('PRAGMA temp.foreign_key_check(wp_postmeta)')['schema']);
    },
    'foreign key check corpus execute ignores trailing semicolon' => static function (TestRunner $t) use ($execute): void {
        $t->same([11], array_column($execute('PRAGMA foreign_key_check(wp_postmeta);')['rows'], 'rowid'));
    },
    'foreign key check corpus accepts pair list column mapping' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLitePragmaForeignKeyCheck::check($tables, [['table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID']]]]);
        $t->same([11], array_column($rows, 'rowid'));
    },
    'foreign key check corpus accepts oid rowid alias' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['oid' => 'c1', 'parent_id' => 9]], 'parent' => [['id' => 1]]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['parent_id' => 'id']]]
        );
        $t->same('c1', $rows[0]['rowid']);
    },
    'foreign key check corpus accepts underscore rowid alias' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['_rowid_' => 7, 'parent_id' => 9]], 'parent' => [['id' => 1]]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['parent_id' => 'id']]]
        );
        $t->same(7, $rows[0]['rowid']);
    },
    'foreign key check corpus reports null rowid when alias absent' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['parent_id' => 9]], 'parent' => [['id' => 1]]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['parent_id' => 'id']]]
        );
        $t->same(null, $rows[0]['rowid']);
    },
    'foreign key check corpus matches text keys exactly' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['rowid' => 1, 'parent_id' => '01']], 'parent' => [['id' => 1]]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['parent_id' => 'id']]]
        );
        $t->same([1], array_column($rows, 'rowid'));
    },
    'foreign key check corpus matches composite parent when all columns match' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['rowid' => 1, 'a' => 5, 'b' => 'x']], 'parent' => [['pa' => 5, 'pb' => 'x']]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['a' => 'pa', 'b' => 'pb']]]
        );
        $t->same([], $rows);
    },
    'foreign key check corpus detects composite parent when second column differs' => static function (TestRunner $t): void {
        $rows = SQLitePragmaForeignKeyCheck::check(
            ['child' => [['rowid' => 1, 'a' => 5, 'b' => 'y']], 'parent' => [['pa' => 5, 'pb' => 'x']]],
            [['table' => 'child', 'parent' => 'parent', 'columns' => ['a' => 'pa', 'b' => 'pb']]]
        );
        $t->same([1], array_column($rows, 'rowid'));
    },
    'foreign key check corpus rejects malformed child table' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['table' => 'bad-name', 'parent' => 'p', 'columns' => ['c' => 'id']]]));
    },
    'foreign key check corpus rejects malformed parent table' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['table' => 'c', 'parent' => 'bad-name', 'columns' => ['c' => 'id']]]));
    },
    'foreign key check corpus rejects empty column mapping' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['table' => 'c', 'parent' => 'p', 'columns' => []]]));
    },
    'foreign key check corpus rejects malformed child column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['table' => 'c', 'parent' => 'p', 'columns' => ['bad-name' => 'id']]]));
    },
    'foreign key check corpus rejects malformed parent column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['table' => 'c', 'parent' => 'p', 'columns' => ['c' => 'bad-name']]]));
    },
    'foreign key check corpus rejects negative fkid' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [['id' => -1, 'table' => 'c', 'parent' => 'p', 'columns' => ['c' => 'id']]]));
    },
    'foreign key check corpus rejects child row missing column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check(['c' => [['rowid' => 1]], 'p' => []], [['table' => 'c', 'parent' => 'p', 'columns' => ['parent_id' => 'id']]]));
    },
    'foreign key check corpus rejects parent row missing column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check(['c' => [['rowid' => 1, 'parent_id' => 1]], 'p' => [[]]], [['table' => 'c', 'parent' => 'p', 'columns' => ['parent_id' => 'id']]]));
    },
    'foreign key check corpus rejects malformed target table' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check([], [], 'bad-name'));
    },
    'foreign key check corpus rejects malformed rowid alias' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::check(['c' => [['rowid' => [], 'parent_id' => 1]], 'p' => []], [['table' => 'c', 'parent' => 'p', 'columns' => ['parent_id' => 'id']]]));
    },
    'foreign key check corpus rejects unsupported pragma shape' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_list(wp_postmeta)', $tables, $foreignKeys));
    },
    'foreign key check corpus rejects equals pragma shape' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_check = wp_postmeta', $tables, $foreignKeys));
    },
    'foreign key check corpus rejects unterminated pragma' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_check(wp_postmeta', $tables, $foreignKeys));
    },
    'foreign key check corpus rejects select wrapper' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyCheck::execute('SELECT * FROM pragma_foreign_key_check', $tables, $foreignKeys));
    },
];

return $tests;
