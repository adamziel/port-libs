<?php

declare(strict_types=1);

use PortLibs\Dolt\DiffTabularRenderer;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

$diffRows = static function (): array {
    return (new TableDiff())->diffTableRows(
        [
            ['pk' => 1, 'val' => 10],
            ['pk' => 2, 'val' => 10],
        ],
        [
            ['pk' => 1, 'val' => 12],
            ['pk' => 3, 'val' => 10],
        ],
        'pk',
        ['pk', 'val'],
    );
};
$schema = static fn (): TableSchema => TableSchema::fromColumns([
    ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'val', 'tag' => 2, 'type' => 'int'],
]);
$procedureDiff = static function (): array {
    return (new TableDiff())->diffTableRows(
        [
            [
                'name' => 'modify1',
                'create_stmt' => "CREATE PROCEDURE modify1() BEGIN\nDECLARE a INT DEFAULT 1;\nSELECT a\n  AS RESULT;\nEND",
            ],
            [
                'name' => 'modify2',
                'create_stmt' => 'CREATE PROCEDURE modify2() SELECT 42',
            ],
        ],
        [
            [
                'name' => 'modify1',
                'create_stmt' => "CREATE PROCEDURE modify1() BEGIN\nSELECT 2\n  AS RESULTING\n  FROM DUAL;\nEND",
            ],
            [
                'name' => 'modify2',
                'create_stmt' => 'CREATE PROCEDURE modify2() SELECT 43',
            ],
        ],
        'name',
        ['name', 'create_stmt'],
    );
};
$procedureSchema = static fn (): TableSchema => TableSchema::fromColumns([
    ['name' => 'name', 'tag' => 1, 'type' => 'varchar(20)', 'primaryKey' => true],
    ['name' => 'create_stmt', 'tag' => 2, 'type' => 'longtext'],
]);

return [
    'dolt tabular diff renderer matches upstream row-mode markers and frame' => static function (TestRunner $t) use ($diffRows, $schema): void {
        $output = (new DiffTabularRenderer())->render('t', $schema(), $diffRows());

        $t->same(implode("\n", [
            'diff --dolt a/t b/t',
            '--- a/t',
            '+++ b/t',
            '+---+----+-----+',
            '|   | pk | val |',
            '+---+----+-----+',
            '| < | 1  | 10  |',
            '| > | 1  | 12  |',
            '| - | 2  | 10  |',
            '| + | 3  | 10  |',
            '+---+----+-----+',
        ]), $output);
    },
    'dolt tabular diff renderer filters row changes by upstream diff type' => static function (TestRunner $t) use ($diffRows, $schema): void {
        $renderer = new DiffTabularRenderer();
        $rows = $diffRows();
        $table = $schema();

        $t->same(implode("\n", [
            'diff --dolt a/t b/t',
            '--- a/t',
            '+++ b/t',
            '+---+----+-----+',
            '|   | pk | val |',
            '+---+----+-----+',
            '| + | 3  | 10  |',
            '+---+----+-----+',
        ]), $renderer->render('t', $table, $rows, ['filter' => 'added']));
        $t->same(implode("\n", [
            'diff --dolt a/t b/t',
            '--- a/t',
            '+++ b/t',
            '+---+----+-----+',
            '|   | pk | val |',
            '+---+----+-----+',
            '| < | 1  | 10  |',
            '| > | 1  | 12  |',
            '+---+----+-----+',
        ]), $renderer->render('t', $table, $rows, ['filter' => 'modified']));
        $t->same(implode("\n", [
            'diff --dolt a/t b/t',
            '--- a/t',
            '+++ b/t',
            '+---+----+-----+',
            '|   | pk | val |',
            '+---+----+-----+',
            '| - | 2  | 10  |',
            '+---+----+-----+',
        ]), $renderer->render('t', $table, $rows, ['filter' => 'removed']));
        $t->same($renderer->render('t', $table, $rows, ['filter' => 'removed']), $renderer->render('t', $table, $rows, ['filter' => 'dropped']));
        $t->same('', $renderer->render('t', $table, $rows, ['filter' => 'renamed']));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render('t', $table, $rows, ['filter' => 'invalid']));
    },
    'dolt tabular diff renderer pads null and multiline cells' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'body', 'tag' => 2, 'type' => 'text'],
        ]);
        $rows = (new TableDiff())->diffTableRows(
            [['pk' => 1, 'body' => null]],
            [['pk' => 1, 'body' => "short\nlonger"]],
            'pk',
            ['pk', 'body'],
        );

        $t->same(implode("\n", [
            'diff --dolt a/t b/t',
            '--- a/t',
            '+++ b/t',
            '+---+----+--------+',
            '|   | pk | body   |',
            '+---+----+--------+',
            '| < | 1  | NULL   |',
            '| > | 1  | short  |',
            '|   |    | longer |',
            '+---+----+--------+',
        ]), (new DiffTabularRenderer())->render('t', $schema, $rows, ['diffMode' => 'row']));
    },
    'dolt tabular diff renderer maps upstream row line in-place and context modes' => static function (TestRunner $t) use ($procedureDiff, $procedureSchema): void {
        $renderer = new DiffTabularRenderer();
        $rows = $procedureDiff();
        $schema = $procedureSchema();

        $t->same(implode("\n", [
            'diff --dolt a/procedures b/procedures',
            '--- a/procedures',
            '+++ b/procedures',
            '+---+---------+---------------------------------------+',
            '|   | name    | create_stmt                           |',
            '+---+---------+---------------------------------------+',
            '| * | modify1 |  CREATE PROCEDURE modify1() BEGIN     |',
            '|   |         | -DECLARE a INT DEFAULT 1;             |',
            '|   |         | -SELECT a                             |',
            '|   |         | -  AS RESULT;                         |',
            '|   |         | +SELECT 2                             |',
            '|   |         | +  AS RESULTING                       |',
            '|   |         | +  FROM DUAL;                         |',
            '|   |         |  END                                  |',
            '| * | modify2 | -CREATE PROCEDURE modify2() SELECT 42 |',
            '|   |         | +CREATE PROCEDURE modify2() SELECT 43 |',
            '+---+---------+---------------------------------------+',
        ]), $renderer->render('procedures', $schema, $rows, ['diffMode' => 'line']));

        $t->same(implode("\n", [
            'diff --dolt a/procedures b/procedures',
            '--- a/procedures',
            '+++ b/procedures',
            '+---+---------+---------------------------------------+',
            '|   | name    | create_stmt                           |',
            '+---+---------+---------------------------------------+',
            '| * | modify1 | CREATE PROCEDURE modify1() BEGIN      |',
            '|   |         | DECLARE a INT DEFAULT 1;              |',
            '|   |         | SELECT a2                             |',
            '|   |         |   AS RESULTING                        |',
            '|   |         |   FROM DUAL;                          |',
            '|   |         | END                                   |',
            '| * | modify2 | CREATE PROCEDURE modify2() SELECT 423 |',
            '+---+---------+---------------------------------------+',
        ]), $renderer->render('procedures', $schema, $rows, ['diffMode' => 'in-place']));

        $t->same(implode("\n", [
            'diff --dolt a/procedures b/procedures',
            '--- a/procedures',
            '+++ b/procedures',
            '+---+---------+--------------------------------------+' ,
            '|   | name    | create_stmt                          |',
            '+---+---------+--------------------------------------+',
            '| * | modify1 |  CREATE PROCEDURE modify1() BEGIN    |',
            '|   |         | -DECLARE a INT DEFAULT 1;            |',
            '|   |         | -SELECT a                            |',
            '|   |         | -  AS RESULT;                        |',
            '|   |         | +SELECT 2                            |',
            '|   |         | +  AS RESULTING                      |',
            '|   |         | +  FROM DUAL;                        |',
            '|   |         |  END                                 |',
            '| < | modify2 | CREATE PROCEDURE modify2() SELECT 42 |',
            '| > | modify2 | CREATE PROCEDURE modify2() SELECT 43 |',
            '+---+---------+--------------------------------------+',
        ]), $renderer->render('procedures', $schema, $rows));

        $t->same($renderer->render('procedures', $schema, $rows), $renderer->render('procedures', $schema, $rows, ['diffMode' => 'context']));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render('procedures', $schema, $rows, ['diffMode' => 'unknown']));
    },
    'wordpress filtered diff tabular example separates migration row review queues' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-filtered-diff-tabular.php';

        $t->contains('| < | 101 | Draft landing', $output['modified']);
        $t->contains('| > | 101 | Published landing', $output['modified']);
        $t->contains('| - | 102 | Legacy page', $output['removed']);
        $t->contains('| + | 103 | Imported resource', $output['added']);
        $t->contains('diff --dolt a/wp_posts b/wp_posts', $output['all']);
        $t->contains('Published landing', $output['all']);
        $t->contains('Legacy page', $output['all']);
        $t->contains('Imported resource', $output['all']);
    },
    'wordpress diff mode example renders multiline block edits for review' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-diff-mode-review.php';

        $t->contains('| < | 701 | Draft import block', $output['row']);
        $t->contains('| > | 701 | Draft import block', $output['row']);
        $t->contains('| * | 701 |  Draft import block', $output['line']);
        $t->contains('|   |     | -<p>Draft import copy.</p>', $output['line']);
        $t->contains('|   |     | +<p>Reviewed import copy.</p>', $output['line']);
        $t->contains('| * | 701 |  Draft import block', $output['context']);
        $t->contains('|   |     | <p>DraftReviewed import copy.</p>', $output['inPlace']);
    },
];
