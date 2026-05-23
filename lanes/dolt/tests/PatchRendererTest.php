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
    'dolt patch create table includes upstream check constraints' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
        ], [
            'checks' => [[
                'name' => 'foo_chk_rvgogafi',
                'expression' => '(`c1` > 3)',
            ]],
        ]);

        $rows = (new PatchRenderer())->rows([[
            'tableName' => 'foo',
            'fromSchema' => null,
            'toSchema' => $schema,
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING']);

        $t->same([
            "CREATE TABLE `foo` (\n"
            . "  `pk` int NOT NULL,\n"
            . "  `c1` int,\n"
            . "  PRIMARY KEY (`pk`),\n"
            . "  CONSTRAINT `foo_chk_rvgogafi` CHECK ((`c1` > 3))\n"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;',
        ], array_column($rows, 'statement'));
        $t->same(['schema'], array_column($rows, 'diff_type'));
    },
    'dolt patch renders default generated and on update column ddl like upstream' => static function (TestRunner $t): void {
        $from = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'title', 'tag' => 2, 'type' => 'varchar(40)', 'default' => 'untitled'],
            ['name' => 'slug', 'tag' => 3, 'type' => 'varchar(80)', 'generated' => "(concat('post-',t.id))"],
            ['name' => 'updated', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
        ]);
        $to = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'title', 'tag' => 2, 'type' => 'varchar(80)', 'default' => 'reviewed'],
            ['name' => 'slug', 'tag' => 3, 'type' => 'varchar(120)', 'generated' => "(concat('wp-',t.id))", 'generatedStored' => true],
            ['name' => 'updated', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
            ['name' => 'status', 'tag' => 5, 'type' => 'varchar(20)', 'default' => 'draft'],
        ]);
        $createSchema = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'post_name', 'tag' => 2, 'type' => 'varchar(200)', 'default' => 'pending'],
            ['name' => 'generated_slug', 'tag' => 3, 'type' => 'varchar(255)', 'generated' => "(concat('wp-',`id`))", 'generatedStored' => true],
            ['name' => 'touched', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
        ]);
        $renderer = new PatchRenderer();

        $alterRows = $renderer->rows([[
            'tableName' => 't',
            'fromSchema' => $from,
            'toSchema' => $to,
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING', 'filter' => 'schema']);
        $createRows = $renderer->rows([[
            'tableName' => 'wp_import_queue',
            'fromSchema' => null,
            'toSchema' => $createSchema,
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING']);

        $t->same([
            "ALTER TABLE `t` MODIFY COLUMN `title` varchar(80) DEFAULT 'reviewed';",
            "ALTER TABLE `t` MODIFY COLUMN `slug` varchar(120) GENERATED ALWAYS AS ((concat('wp-',t.id))) STORED;",
            "ALTER TABLE `t` ADD `status` varchar(20) DEFAULT 'draft';",
        ], array_column($alterRows, 'statement'));
        $t->same([
            "CREATE TABLE `wp_import_queue` (\n"
            . "  `id` int NOT NULL,\n"
            . "  `post_name` varchar(200) DEFAULT 'pending',\n"
            . "  `generated_slug` varchar(255) GENERATED ALWAYS AS ((concat('wp-',`id`))) STORED,\n"
            . "  `touched` timestamp DEFAULT 'CURRENT_TIMESTAMP' ON UPDATE CURRENT_TIMESTAMP,\n"
            . "  PRIMARY KEY (`id`)\n"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;',
        ], array_column($createRows, 'statement'));
        $t->throws(InvalidArgumentException::class, static fn () => TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'generatedStored' => true],
        ]));
    },
    'dolt patch renders auto increment columns and primary key type changes like upstream' => static function (TestRunner $t): void {
        $postsSchema = TableSchema::fromColumns([
            ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true, 'autoIncrement' => true],
            ['name' => 'post_title', 'tag' => 2, 'type' => 'varchar(255)'],
        ]);
        $fromPk = TableSchema::fromColumns([
            ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'post_title', 'tag' => 2, 'type' => 'varchar(255)'],
        ]);
        $toPk = TableSchema::fromColumns([
            ['name' => 'ID', 'tag' => 1, 'type' => 'int', 'primaryKey' => true, 'autoIncrement' => true],
            ['name' => 'post_title', 'tag' => 2, 'type' => 'varchar(255)'],
        ]);
        $metadataOnlyAutoIncrement = TableSchema::fromColumns([
            ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true, 'autoIncrement' => true],
            ['name' => 'post_title', 'tag' => 2, 'type' => 'varchar(255)'],
        ]);
        $renderer = new PatchRenderer();

        $createRows = $renderer->rows([[
            'tableName' => 'wp_posts',
            'fromSchema' => null,
            'toSchema' => $postsSchema,
            'primaryKey' => 'ID',
            'toRows' => [
                ['ID' => 1, 'post_title' => 'First'],
                ['ID' => 2, 'post_title' => 'Second'],
            ],
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING']);
        $warnings = [];
        $typeChangeRows = $renderer->rows([[
            'tableName' => 'wp_posts',
            'fromSchema' => $fromPk,
            'toSchema' => $toPk,
            'primaryKey' => 'ID',
            'fromRows' => [['ID' => 1, 'post_title' => 'First']],
            'toRows' => [['ID' => 1, 'post_title' => 'First']],
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING'], $warnings);
        $metadataOnlyRows = $renderer->rows([[
            'tableName' => 'wp_posts',
            'fromSchema' => $fromPk,
            'toSchema' => $metadataOnlyAutoIncrement,
        ]], ['filter' => 'schema']);

        $t->same([
            "CREATE TABLE `wp_posts` (\n"
            . "  `ID` bigint NOT NULL AUTO_INCREMENT,\n"
            . "  `post_title` varchar(255),\n"
            . "  PRIMARY KEY (`ID`)\n"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;',
            "INSERT INTO `wp_posts` (`ID`,`post_title`) VALUES (1,'First');",
            "INSERT INTO `wp_posts` (`ID`,`post_title`) VALUES (2,'Second');",
        ], array_column($createRows, 'statement'));
        $t->same([
            'ALTER TABLE `wp_posts` MODIFY COLUMN `ID` int NOT NULL AUTO_INCREMENT;',
            'ALTER TABLE `wp_posts` DROP PRIMARY KEY;',
            'ALTER TABLE `wp_posts` ADD PRIMARY KEY (ID);',
        ], array_column($typeChangeRows, 'statement'));
        $t->same(['schema', 'schema', 'schema'], array_column($typeChangeRows, 'diff_type'));
        $t->same([], $metadataOnlyRows);
        $t->same(1, count($warnings));
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $warnings[0]['code']);
        $t->throws(InvalidArgumentException::class, static fn () => TableSchema::fromColumns([
            ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'autoIncrement' => 'yes'],
        ]));
    },
    'dolt patch omits metadata-only column and check constraint patch rows like upstream' => static function (TestRunner $t): void {
        $fromColumns = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'title', 'tag' => 2, 'type' => 'varchar(80)', 'default' => 'untitled'],
            ['name' => 'slug', 'tag' => 3, 'type' => 'varchar(120)', 'generated' => "(concat('wp-',id))"],
            ['name' => 'updated', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
            ['name' => 'status', 'tag' => 5, 'type' => 'varchar(20)'],
        ]);
        $toColumns = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'title', 'tag' => 2, 'type' => 'varchar(80)', 'default' => 'reviewed'],
            ['name' => 'slug', 'tag' => 3, 'type' => 'varchar(120)', 'generated' => "(concat('import-',id))", 'generatedStored' => true],
            ['name' => 'updated', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'onUpdate' => 'CURRENT_TIMESTAMP'],
            ['name' => 'status', 'tag' => 5, 'type' => 'varchar(20)', 'constraints' => ['not_null']],
        ]);
        $withoutCheck = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'status', 'tag' => 2, 'type' => 'varchar(20)'],
        ]);
        $withCheck = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'status', 'tag' => 2, 'type' => 'varchar(20)'],
        ], [
            'checks' => [[
                'name' => 'status_chk',
                'expression' => "(`status` in ('ready','failed'))",
            ]],
        ]);
        $modifiedCheck = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'status', 'tag' => 2, 'type' => 'varchar(20)'],
        ], [
            'checks' => [[
                'name' => 'status_chk',
                'expression' => "(`status` in ('queued','ready','failed'))",
                'enforced' => false,
            ]],
        ]);
        $renderer = new PatchRenderer();

        $metadataRows = $renderer->rows([[
            'tableName' => 'wp_import_queue',
            'fromSchema' => $fromColumns,
            'toSchema' => $toColumns,
        ]], ['filter' => 'schema']);
        $addCheckRows = $renderer->rows([[
            'tableName' => 'wp_import_queue',
            'fromSchema' => $withoutCheck,
            'toSchema' => $withCheck,
        ]], ['filter' => 'schema']);
        $modifyCheckRows = $renderer->rows([[
            'tableName' => 'wp_import_queue',
            'fromSchema' => $withCheck,
            'toSchema' => $modifiedCheck,
        ]], ['filter' => 'schema']);
        $dropCheckRows = $renderer->rows([[
            'tableName' => 'wp_import_queue',
            'fromSchema' => $withCheck,
            'toSchema' => $withoutCheck,
        ]], ['filter' => 'schema']);

        $t->same(['added'], array_column(TableSchema::diffChecks($withoutCheck, $withCheck), 'diff_type'));
        $t->same(['modified'], array_column(TableSchema::diffChecks($withCheck, $modifiedCheck), 'diff_type'));
        $t->same(['removed'], array_column(TableSchema::diffChecks($withCheck, $withoutCheck), 'diff_type'));
        $t->same([], $metadataRows);
        $t->same([], $addCheckRows);
        $t->same([], $modifyCheckRows);
        $t->same([], $dropCheckRows);
    },
    'dolt patch renders table collation changes like upstream' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);
        $accentInsensitive = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ], [
            'collation' => 'utf8mb4_0900_ai_ci',
        ]);
        $utf8mb3 = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ], [
            'collation' => 'utf8mb3_general_ci',
        ]);
        $renderer = new PatchRenderer();

        $reverseRows = $renderer->rows([[
            'tableName' => 't',
            'fromSchema' => $accentInsensitive,
            'toSchema' => $schema,
            'primaryKey' => 'pk',
            'fromRows' => [['pk' => 1]],
            'toRows' => [],
        ]]);
        $forwardRows = $renderer->rows([[
            'tableName' => 't',
            'fromSchema' => $accentInsensitive,
            'toSchema' => $utf8mb3,
            'primaryKey' => 'pk',
            'fromRows' => [['pk' => 1]],
            'toRows' => [['pk' => 1], ['pk' => 2]],
        ]]);

        $t->same([
            "ALTER TABLE `t` COLLATE='utf8mb4_0900_bin';",
            'DELETE FROM `t` WHERE `pk`=1;',
        ], array_column($reverseRows, 'statement'));
        $t->same([
            "ALTER TABLE `t` COLLATE='utf8mb3_general_ci';",
            'INSERT INTO `t` (`pk`) VALUES (2);',
        ], array_column($forwardRows, 'statement'));
        $t->same(['schema', 'data'], array_column($reverseRows, 'diff_type'));
        $t->same(['schema', 'data'], array_column($forwardRows, 'diff_type'));
    },
    'dolt patch renders target row size changes after collation like upstream' => static function (TestRunner $t): void {
        $default = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'body', 'tag' => 2, 'type' => 'longtext'],
        ]);
        $wideRows = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'body', 'tag' => 2, 'type' => 'longtext'],
        ], [
            'collation' => 'utf8mb4_unicode_ci',
            'targetRowSize' => 4096,
        ]);
        $renderer = new PatchRenderer();

        $forwardRows = $renderer->rows([[
            'tableName' => 't',
            'fromSchema' => $default,
            'toSchema' => $wideRows,
            'primaryKey' => 'pk',
            'fromRows' => [['pk' => 1, 'body' => 'old']],
            'toRows' => [['pk' => 1, 'body' => 'new']],
        ]]);
        $reverseRows = $renderer->rows([[
            'tableName' => 't',
            'fromSchema' => $wideRows,
            'toSchema' => $default,
            'primaryKey' => 'pk',
            'fromRows' => [['pk' => 1, 'body' => 'new']],
            'toRows' => [['pk' => 1, 'body' => 'old']],
        ]]);

        $t->same([
            "ALTER TABLE `t` COLLATE='utf8mb4_unicode_ci';",
            'ALTER TABLE `t` TARGET_ROW_SIZE=4096;',
            "UPDATE `t` SET `body`='new' WHERE `pk`=1;",
        ], array_column($forwardRows, 'statement'));
        $t->same([
            "ALTER TABLE `t` COLLATE='utf8mb4_0900_bin';",
            'ALTER TABLE `t` TARGET_ROW_SIZE=2048;',
            "UPDATE `t` SET `body`='old' WHERE `pk`=1;",
        ], array_column($reverseRows, 'statement'));
        $t->same(['schema', 'schema', 'data'], array_column($forwardRows, 'diff_type'));
        $t->throws(InvalidArgumentException::class, static fn () => TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ], ['targetRowSize' => 70000]));
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
    'dolt patch rows hex encode binary data statements' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'varbinary(16)', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'binary(16)'],
        ]);
        $rows = (new PatchRenderer())->rows([[
            'tableName' => 't',
            'fromSchema' => $schema,
            'toSchema' => $schema,
            'diffRows' => [
                ['diff_type' => 'added', 'to_pk' => "\x01\x23\x45", 'to_c1' => null],
                ['diff_type' => 'modified', 'from_pk' => "\x42", 'from_c1' => null, 'to_pk' => "\x42", 'to_c1' => str_pad("\xee\xee", 16, "\0")],
            ],
        ]], ['fromCommit' => 'HEAD~', 'toCommit' => 'HEAD', 'filter' => 'data']);

        $t->same(['data', 'data'], array_column($rows, 'diff_type'));
        $t->same(['HEAD~', 'HEAD'], [$rows[0]['from_commit_hash'], $rows[0]['to_commit_hash']]);
        $t->same([
            'INSERT INTO `t` (`pk`,`c1`) VALUES (0x012345,NULL);',
            'UPDATE `t` SET `c1`=0xeeee0000000000000000000000000000 WHERE `pk`=0x42;',
        ], array_column($rows, 'statement'));
    },
    'dolt patch skips data and records upstream warning when primary keys change' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'id_ext', 'tag' => 2, 'type' => 'int'],
            ['name' => 'value', 'tag' => 3, 'type' => 'int'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'id_ext', 'tag' => 2, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'value', 'tag' => 3, 'type' => 'int'],
        ]);
        $table = [
            'tableName' => 'parent',
            'fromSchema' => $fromSchema,
            'toSchema' => $toSchema,
            'primaryKey' => ['id', 'id_ext'],
            'fromRows' => [
                ['id' => 0, 'id_ext' => 1, 'value' => 2],
            ],
            'toRows' => [
                ['id' => 0, 'id_ext' => 1, 'value' => 3],
            ],
        ];
        $renderer = new PatchRenderer();

        $schemaWarnings = [];
        $schemaRows = $renderer->rows([$table], ['fromCommit' => 'HEAD', 'toCommit' => 'STAGED', 'filter' => 'schema'], $schemaWarnings);
        $warnings = [];
        $rows = $renderer->rows([$table], ['fromCommit' => 'HEAD', 'toCommit' => 'STAGED'], $warnings);
        $dataWarnings = [];
        $dataRows = $renderer->rows([$table], ['fromCommit' => 'HEAD', 'toCommit' => 'STAGED', 'filter' => 'data'], $dataWarnings);

        $t->same([], $schemaWarnings);
        $t->same(['schema', 'schema'], array_column($schemaRows, 'diff_type'));
        $t->same(['schema', 'schema'], array_column($rows, 'diff_type'));
        $t->same([], $dataRows);
        $t->same(1, count($warnings));
        $t->same($warnings, $dataWarnings);
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $warnings[0]['code']);
        $t->same("Primary key sets differ between revisions for table 'parent', skipping data diff", $warnings[0]['message']);
    },
    'dolt patch orders secondary index foreign key and primary key ddl like upstream' => static function (TestRunner $t): void {
        $parentBefore = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'id_ext', 'tag' => 2, 'type' => 'int'],
            ['name' => 'v1', 'tag' => 3, 'type' => 'int'],
            ['name' => 'v2', 'tag' => 4, 'type' => "text COMMENT 'tag:1'"],
        ], [
            'indexes' => [['name' => 'v1', 'columns' => ['v1']]],
        ]);
        $parentAfter = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'id_ext', 'tag' => 2, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'v1', 'tag' => 3, 'type' => 'int'],
            ['name' => 'v2', 'tag' => 4, 'type' => "text COMMENT 'tag:1'"],
        ], [
            'indexes' => [['name' => 'v1', 'columns' => ['v1']]],
        ]);
        $childBefore = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 5, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'v1', 'tag' => 6, 'type' => 'int'],
        ]);
        $childAfter = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 5, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'v1', 'tag' => 6, 'type' => 'int'],
        ], [
            'indexes' => [['name' => 'fk_named', 'columns' => ['v1']]],
            'foreignKeys' => [[
                'name' => 'fk_named',
                'columns' => ['v1'],
                'referencedTable' => 'parent',
                'referencedColumns' => ['v1'],
            ]],
        ]);
        $tables = [
            ['tableName' => 'parent', 'fromSchema' => $parentBefore, 'toSchema' => $parentAfter, 'primaryKey' => ['id', 'id_ext'], 'fromRows' => [['id' => 0, 'id_ext' => 1, 'v1' => 2, 'v2' => null]], 'toRows' => [['id' => 0, 'id_ext' => 1, 'v1' => 2, 'v2' => null]]],
            ['tableName' => 'child', 'fromSchema' => $childBefore, 'toSchema' => $childAfter, 'primaryKey' => 'id'],
        ];
        $warnings = [];

        $rows = (new PatchRenderer())->rows($tables, ['fromCommit' => 'HEAD', 'toCommit' => 'STAGED'], $warnings);
        $createRows = (new PatchRenderer())->rows([
            ['tableName' => 'child', 'fromSchema' => null, 'toSchema' => $childAfter, 'primaryKey' => 'id'],
            ['tableName' => 'parent', 'fromSchema' => null, 'toSchema' => $parentAfter, 'primaryKey' => ['id', 'id_ext'], 'toRows' => [['id' => 0, 'id_ext' => 1, 'v1' => 2, 'v2' => null]]],
        ], ['fromCommit' => 'HEAD~', 'toCommit' => 'WORKING']);

        $t->same([
            'ALTER TABLE `child` ADD INDEX `fk_named`(`v1`);',
            'ALTER TABLE `child` ADD CONSTRAINT `fk_named` FOREIGN KEY (`v1`) REFERENCES `parent` (`v1`);',
            'ALTER TABLE `parent` DROP PRIMARY KEY;',
            'ALTER TABLE `parent` ADD PRIMARY KEY (id,id_ext);',
        ], array_column($rows, 'statement'));
        $t->same(['child', 'child', 'parent', 'parent'], array_column($rows, 'table_name'));
        $t->same(1, count($warnings));
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $warnings[0]['code']);
        $t->contains("Primary key sets differ between revisions for table 'parent'", $warnings[0]['message']);
        $t->same("CREATE TABLE `child` (\n  `id` int NOT NULL,\n  `v1` int,\n  PRIMARY KEY (`id`),\n  KEY `fk_named` (`v1`),\n  CONSTRAINT `fk_named` FOREIGN KEY (`v1`) REFERENCES `parent` (`v1`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;", $createRows[0]['statement']);
        $t->same("CREATE TABLE `parent` (\n  `id` int NOT NULL,\n  `id_ext` int NOT NULL,\n  `v1` int,\n  `v2` text COMMENT 'tag:1',\n  PRIMARY KEY (`id`,`id_ext`),\n  KEY `v1` (`v1`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;", $createRows[1]['statement']);
        $t->same('INSERT INTO `parent` (`id`,`id_ext`,`v1`,`v2`) VALUES (0,1,2,NULL);', $createRows[2]['statement']);
    },
    'dolt patch modifies and drops secondary indexes and foreign keys like upstream' => static function (TestRunner $t): void {
        $from = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'legacy_id', 'tag' => 2, 'type' => 'int'],
            ['name' => 'new_id', 'tag' => 3, 'type' => 'int'],
            ['name' => 'term_id', 'tag' => 4, 'type' => 'int'],
        ], [
            'indexes' => [
                ['name' => 'fk_review', 'columns' => ['legacy_id']],
                ['name' => 'fk_term', 'columns' => ['term_id']],
            ],
            'foreignKeys' => [
                [
                    'name' => 'fk_review',
                    'columns' => ['legacy_id'],
                    'referencedTable' => 'parent',
                    'referencedColumns' => ['legacy_id'],
                    'onDelete' => 'CASCADE',
                ],
                [
                    'name' => 'fk_term',
                    'columns' => ['term_id'],
                    'referencedTable' => 'terms',
                    'referencedColumns' => ['term_id'],
                ],
            ],
        ]);
        $to = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'legacy_id', 'tag' => 2, 'type' => 'int'],
            ['name' => 'new_id', 'tag' => 3, 'type' => 'int'],
            ['name' => 'term_id', 'tag' => 4, 'type' => 'int'],
        ], [
            'indexes' => [
                ['name' => 'fk_review', 'columns' => ['new_id']],
            ],
            'foreignKeys' => [
                [
                    'name' => 'fk_review',
                    'columns' => ['new_id'],
                    'referencedTable' => 'parent',
                    'referencedColumns' => ['new_id'],
                    'onUpdate' => 'CASCADE',
                ],
            ],
        ]);

        $rows = (new PatchRenderer())->rows([[
            'tableName' => 'child',
            'fromSchema' => $from,
            'toSchema' => $to,
        ]], ['fromCommit' => 'HEAD', 'toCommit' => 'WORKING']);
        $createRows = (new PatchRenderer())->rows([[
            'tableName' => 'child',
            'fromSchema' => null,
            'toSchema' => $from,
        ]], ['fromCommit' => 'EMPTY', 'toCommit' => 'HEAD']);

        $t->same([
            'ALTER TABLE `child` DROP INDEX `fk_review`;',
            'ALTER TABLE `child` ADD INDEX `fk_review`(`new_id`);',
            'ALTER TABLE `child` DROP INDEX `fk_term`;',
            'ALTER TABLE `child` DROP FOREIGN KEY `fk_review`;',
            'ALTER TABLE `child` ADD CONSTRAINT `fk_review` FOREIGN KEY (`new_id`) REFERENCES `parent` (`new_id`);',
            'ALTER TABLE `child` DROP FOREIGN KEY `fk_term`;',
        ], array_column($rows, 'statement'));
        $t->same(['schema', 'schema', 'schema', 'schema', 'schema', 'schema'], array_column($rows, 'diff_type'));
        $t->contains('ON DELETE CASCADE', $createRows[0]['statement']);
        $t->true(!str_contains($rows[4]['statement'], 'ON UPDATE CASCADE'), 'ALTER ADD foreign key patch statement should omit referential actions.');
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
    'wordpress binary patch review example exposes media hash SQL literals' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-binary-patch-review.php';
        $output = require __DIR__ . '/../examples/wordpress-binary-patch-review.php';

        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same(['data', 'data'], array_column($output['rows'], 'diff_type'));
        $t->contains('0x77700001', $output['statements'][0]);
        $t->contains('0x696d6700', $output['statements'][1]);
    },
];
