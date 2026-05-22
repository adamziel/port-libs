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
];
