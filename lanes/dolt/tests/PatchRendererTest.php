<?php

declare(strict_types=1);

use PortLibs\Dolt\PatchRenderer;
use PortLibs\Dolt\TableSchema;

return [
    'dolt patch rows emit schema before data and restart order for diff type partitions' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'varchar(20)'],
            ['name' => 'c2', 'tag' => 3, 'type' => 'varchar(20)'],
        ]);
        $renderer = new PatchRenderer();
        $table = [
            'tableName' => 't',
            'fromSchema' => null,
            'toSchema' => $schema,
            'primaryKey' => 'pk',
            'fromRows' => [],
            'toRows' => [
                ['pk' => 1, 'c1' => 'one', 'c2' => 'two'],
            ],
        ];

        $all = $renderer->rows([$table], ['fromCommit' => 'c1', 'toCommit' => 'c2']);
        $data = $renderer->rows([$table], ['fromCommit' => 'c1', 'toCommit' => 'c2', 'filter' => 'data']);
        $schemaOnly = $renderer->rows([$table], ['fromCommit' => 'c1', 'toCommit' => 'c2', 'filter' => 'schema']);

        $t->same([1, 2], array_column($all, 'statement_order'));
        $t->same(['schema', 'data'], array_column($all, 'diff_type'));
        $t->same("CREATE TABLE `t` (\n  `pk` int NOT NULL,\n  `c1` varchar(20),\n  `c2` varchar(20),\n  PRIMARY KEY (`pk`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;", $all[0]['statement']);
        $t->same("INSERT INTO `t` (`pk`,`c1`,`c2`) VALUES (1,'one','two');", $all[1]['statement']);
        $t->same([1], array_column($data, 'statement_order'));
        $t->same(['data'], array_column($data, 'diff_type'));
        $t->same([1], array_column($schemaOnly, 'statement_order'));
        $t->same(['schema'], array_column($schemaOnly, 'diff_type'));
    },
    'dolt patch ddl changes follow upstream column diff ordering' => static function (TestRunner $t): void {
        $from = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'a', 'tag' => 2, 'type' => 'int'],
            ['name' => 'b', 'tag' => 3, 'type' => 'int'],
            ['name' => 'c', 'tag' => 4, 'type' => 'int'],
        ]);
        $to = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'a', 'tag' => 2, 'type' => "varchar(100) COMMENT 'foo'"],
            ['name' => 'z', 'tag' => 4, 'type' => 'int'],
            ['name' => 'd', 'tag' => 5, 'type' => 'int'],
        ]);

        $rows = (new PatchRenderer())->rows([[
            'tableName' => 't',
            'fromSchema' => $from,
            'toSchema' => $to,
        ]], ['filter' => 'schema']);

        $t->same([
            "ALTER TABLE `t` MODIFY COLUMN `a` varchar(100) COMMENT 'foo';",
            'ALTER TABLE `t` DROP `b`;',
            'ALTER TABLE `t` RENAME COLUMN `c` TO `z`;',
            'ALTER TABLE `t` ADD `d` int;',
        ], array_column($rows, 'statement'));
        $t->same([1, 2, 3, 4], array_column($rows, 'statement_order'));
    },
    'dolt patch skips drop-table data statements and emits reverse delete patches' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'body', 'tag' => 2, 'type' => 'text'],
        ]);
        $renderer = new PatchRenderer();
        $drop = $renderer->rows([[
            'tableName' => 'wp_old_imports',
            'fromSchema' => $schema,
            'toSchema' => null,
            'primaryKey' => 'pk',
            'fromRows' => [['pk' => 1, 'body' => 'old']],
            'toRows' => [],
        ]]);
        $reverse = $renderer->rows([[
            'tableName' => 'wp_posts',
            'fromSchema' => $schema,
            'toSchema' => $schema,
            'primaryKey' => 'pk',
            'fromRows' => [
                ['pk' => 1, 'body' => 'one'],
                ['pk' => 2, 'body' => 'two'],
            ],
            'toRows' => [
                ['pk' => 1, 'body' => 'one'],
            ],
        ]], ['filter' => 'data']);

        $t->same([['statement_order' => 1, 'from_commit_hash' => 'FROM', 'to_commit_hash' => 'TO', 'table_name' => 'wp_old_imports', 'diff_type' => 'schema', 'statement' => 'DROP TABLE `wp_old_imports`;']], $drop);
        $t->same('DELETE FROM `wp_posts` WHERE `pk`=2;', $reverse[0]['statement']);
        $t->same(1, $reverse[0]['statement_order']);
    },
    'dolt patch maps keyless data rows as repeated data statements' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-keyless-import-log.php';
        $rows = (new PatchRenderer())->rows([[
            'tableName' => $fixture['tableName'],
            'fromSchema' => $fixture['schema'],
            'toSchema' => $fixture['schema'],
            'keyless' => true,
            'columns' => $fixture['columns'],
            'fromRows' => $fixture['fromRows'],
            'toRows' => $fixture['toRows'],
        ]], ['filter' => 'data']);

        $t->same(['data', 'data', 'data'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 3], array_column($rows, 'statement_order'));
        $t->contains("DELETE FROM `wp_import_log` WHERE `event_type`='scan' AND `message`='started media scan'", $rows[0]['statement']);
        $t->contains("INSERT INTO `wp_import_log` (`event_type`,`message`,`created_gmt`) VALUES ('post','queued post 501'", $rows[1]['statement']);
        $t->contains("INSERT INTO `wp_import_log` (`event_type`,`message`,`created_gmt`) VALUES ('media','finished media scan'", $rows[2]['statement']);
    },
    'wordpress patch review example separates schema and data queues' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-review.php';

        $t->same(['schema', 'schema'], array_column($output['schema'], 'diff_type'));
        $t->contains('ALTER TABLE `wp_posts` RENAME COLUMN `post_status` TO `post_state`;', $output['schema'][0]['statement']);
        $t->contains('ALTER TABLE `wp_posts` ADD `import_batch` varchar(40);', $output['schema'][1]['statement']);
        $t->true(count($output['data']) >= 3);
        $t->same([1, 2, 3], array_slice(array_column($output['data'], 'statement_order'), 0, 3));
        $t->contains('INSERT INTO `wp_import_log`', $output['data'][0]['statement']);
        $t->contains('UPDATE `wp_posts` SET', $output['data'][1]['statement']);
        $t->contains('INSERT INTO `wp_posts`', $output['data'][2]['statement']);
        $t->same(5, count($output['all']));
    },
];
