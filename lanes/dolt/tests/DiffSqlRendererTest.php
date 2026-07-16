<?php

declare(strict_types=1);

use PortLibs\Dolt\DiffSqlRenderer;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

return [
    'dolt diff sql renderer filters row changes by upstream diff type' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'val', 'tag' => 2, 'type' => 'int'],
        ]);
        $rows = (new TableDiff())->diffTableRows(
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
        $renderer = new DiffSqlRenderer();

        $t->same('INSERT INTO `t` (`pk`,`val`) VALUES (3,10);', $renderer->render('t', $schema, $rows, ['filter' => 'added']));
        $t->same('UPDATE `t` SET `val`=12 WHERE `pk`=1;', $renderer->render('t', $schema, $rows, ['filter' => 'modified']));
        $t->same('DELETE FROM `t` WHERE `pk`=2;', $renderer->render('t', $schema, $rows, ['filter' => 'removed']));
        $t->same('DELETE FROM `t` WHERE `pk`=2;', $renderer->render('t', $schema, $rows, ['filter' => 'dropped']));
        $t->same('', $renderer->render('t', $schema, $rows, ['filter' => 'renamed']));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render('t', $schema, $rows, ['filter' => 'invalid']));
    },
    'dolt diff sql renderer emits insert update and delete statements in row order' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'name', 'tag' => 2, 'type' => 'varchar(255)'],
            ['name' => 'published', 'tag' => 3, 'type' => 'boolean'],
        ]);
        $rows = (new TableDiff())->diffTableRows(
            [
                ['pk' => 1, 'name' => "Old's post", 'published' => false],
                ['pk' => 2, 'name' => 'Remove me', 'published' => true],
            ],
            [
                ['pk' => 1, 'name' => "New's post", 'published' => true],
                ['pk' => 3, 'name' => 'Add me', 'published' => false],
            ],
            'pk',
            ['pk', 'name', 'published'],
        );

        $t->same(implode("\n", [
            "UPDATE `wp_posts` SET `name`='New\\'s post',`published`=1 WHERE `pk`=1;",
            'DELETE FROM `wp_posts` WHERE `pk`=2;',
            "INSERT INTO `wp_posts` (`pk`,`name`,`published`) VALUES (3,'Add me',0);",
        ]), (new DiffSqlRenderer())->render('wp_posts', $schema, $rows));
    },
    'dolt diff sql renderer hex encodes binary and varbinary values like upstream patches' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'varbinary(16)', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'binary(16)'],
        ]);
        $rows = [
            [
                'diff_type' => TableDiff::DIFF_ADDED,
                'to_pk' => "\x01\x23\x45",
                'to_c1' => null,
            ],
            [
                'diff_type' => TableDiff::DIFF_ADDED,
                'to_pk' => "\x05\x43\x21",
                'to_c1' => str_pad('efg_!4', 16, "\0"),
            ],
            [
                'diff_type' => TableDiff::DIFF_MODIFIED,
                'from_pk' => "\x42",
                'from_c1' => null,
                'to_pk' => "\x42",
                'to_c1' => str_pad("\xee\xee", 16, "\0"),
            ],
        ];

        $t->same(implode("\n", [
            'INSERT INTO `t` (`pk`,`c1`) VALUES (0x012345,NULL);',
            'INSERT INTO `t` (`pk`,`c1`) VALUES (0x054321,0x6566675f213400000000000000000000);',
            'UPDATE `t` SET `c1`=0xeeee0000000000000000000000000000 WHERE `pk`=0x42;',
        ]), (new DiffSqlRenderer())->render('t', $schema, $rows));

        $t->throws(InvalidArgumentException::class, static fn () => (new DiffSqlRenderer())->render('t', $schema, [[
            'diff_type' => TableDiff::DIFF_ADDED,
            'to_pk' => 1,
            'to_c1' => null,
        ]]));
    },
    'wordpress filtered diff sql example separates migration row review queues' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-filtered-diff-sql.php';

        $t->contains('UPDATE `wp_posts` SET', $output['modified']);
        $t->contains("`post_title`='Published landing'", $output['modified']);
        $t->contains('DELETE FROM `wp_posts` WHERE `ID`=102;', $output['removed']);
        $t->contains("INSERT INTO `wp_posts` (`ID`,`post_title`,`post_status`,`post_modified_gmt`) VALUES (103,'Imported resource','publish','2026-05-22 08:15:00');", $output['added']);
        $t->contains('Published landing', $output['all']);
        $t->contains('DELETE FROM `wp_posts` WHERE `ID`=102;', $output['all']);
        $t->contains('Imported resource', $output['all']);
    },
    'dolt diff sql renderer uses every keyless column for delete predicates' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-keyless-import-log.php';
        $rows = (new TableDiff())->keylessDiffTableRows(
            $fixture['fromRows'],
            $fixture['toRows'],
            $fixture['columns'],
        );
        $output = (new DiffSqlRenderer())->render($fixture['tableName'], $fixture['schema'], $rows);

        $t->contains("INSERT INTO `wp_import_log` (`event_type`,`message`,`created_gmt`) VALUES ('post','queued post 501','2026-05-22 09:01:00');", $output);
        $t->contains("INSERT INTO `wp_import_log` (`event_type`,`message`,`created_gmt`) VALUES ('media','finished media scan','2026-05-22 09:05:00');", $output);
        $t->contains("DELETE FROM `wp_import_log` WHERE `event_type`='scan' AND `message`='started media scan' AND `created_gmt`='2026-05-22 09:00:00';", $output);
        $t->same(2, substr_count($output, 'INSERT INTO'));
        $t->same(1, substr_count($output, 'DELETE FROM'));
        $t->same('', (new DiffSqlRenderer())->render($fixture['tableName'], $fixture['schema'], $rows, ['filter' => 'modified']));
    },
    'wordpress keyless import log sql example exposes duplicate row deltas' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-keyless-import-log-diff.php';

        $t->contains('queued post 501', $output['sqlAdded']);
        $t->contains('finished media scan', $output['sqlAdded']);
        $t->contains("DELETE FROM `wp_import_log` WHERE `event_type`='scan'", $output['sqlRemoved']);
        $t->true(!str_contains($output['sqlRemoved'], 'INSERT INTO'));
        $t->same(3, count($output['rows']));
    },
];
