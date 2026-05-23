<?php

declare(strict_types=1);

use PortLibs\Dolt\SchemaShowRenderer;
use PortLibs\Dolt\TableSchema;

$checkSchema = static function (array $columns, array $options = []): TableSchema {
    return TableSchema::fromColumns($columns, $options + [
        'checks' => [[
            'name' => 'foo_chk_rvgogafi',
            'expression' => '(`c1` > 3)',
        ]],
    ]);
};

return [
    'dolt schema show renders create table statements and skips hidden internal tables' => static function (TestRunner $t) use ($checkSchema): void {
        $foo = $checkSchema([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ['name' => 'j', 'tag' => 3, 'type' => 'int'],
        ]);
        $bar = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);

        $output = (new SchemaShowRenderer())->render([
            'dolt_docs' => $bar,
            'foo' => $foo,
            'bar' => $bar,
            'dolt_fulltext_foo_idx' => $bar,
        ]);

        $t->contains('bar @ working', $output);
        $t->contains('foo @ working', $output);
        $t->contains('CREATE TABLE `foo`', $output);
        $t->contains('CONSTRAINT `foo_chk_rvgogafi` CHECK ((`c1` > 3))', $output);
        $t->true(!str_contains($output, 'dolt_docs'), 'dolt schema show should hide dolt_docs when listing all tables.');
        $t->true(!str_contains($output, 'dolt_fulltext_foo_idx'), 'dolt schema show should skip fulltext backing tables.');
        $t->same('No tables in working set', (new SchemaShowRenderer())->render([]));
    },
    'dolt schema show preserves check constraints across upstream schema-edit boundaries' => static function (TestRunner $t) use ($checkSchema): void {
        $renderer = new SchemaShowRenderer();
        $schemas = [
            'adding column' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
                ['name' => 'j', 'tag' => 3, 'type' => 'int'],
            ]),
            'renaming unrelated column' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
                ['name' => 'j2', 'tag' => 3, 'type' => 'int'],
            ]),
            'modifying unrelated column' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
                ['name' => 'j', 'tag' => 3, 'type' => "int COMMENT 'j column'"],
            ]),
            'dropping unrelated column' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ]),
            'adding primary key' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ]),
            'dropping primary key' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int'],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ]),
            'renaming table' => $checkSchema([
                ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
                ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ]),
        ];

        foreach ($schemas as $label => $schema) {
            $tableName = $label === 'renaming table' ? 'foo2' : 'foo';
            $output = $renderer->render([$tableName => $schema], [$tableName], 'WORKING');

            $t->contains($tableName . ' @ WORKING', $output);
            $t->contains('CHECK', $output);
            $t->contains('`c1` > 3', $output);
        }
    },
    'dolt schema show requested table handling follows upstream stdout boundary' => static function (TestRunner $t) use ($checkSchema): void {
        $schema = $checkSchema([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
        ]);
        $renderer = new SchemaShowRenderer();

        $output = $renderer->render(['Foo' => $schema], ['foo', 'missing'], 'HEAD~1');

        $t->contains('foo @ HEAD~1', $output);
        $t->same(['missing'], $renderer->missingTables(['Foo' => $schema], ['foo', 'missing']));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render(['' => $schema]));
        $t->throws(InvalidArgumentException::class, static fn () => $renderer->render(['t' => $schema], ['']));
    },
    'wordpress schema show check survival example renders migration audit guards' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-schema-show-check-survival.php';
        $output = require __DIR__ . '/../examples/wordpress-schema-show-check-survival.php';

        $t->contains($fixture['expectedTableHeader'], $output['schemaShow']);
        foreach ($fixture['expectedCheckFragments'] as $fragment) {
            $t->contains($fragment, $output['schemaShow']);
        }
        $t->same(['wp_missing_import_audit'], $output['missingTables']);
        $t->true(!str_contains($output['schemaShow'], 'dolt_docs'), 'WordPress schema show example should not show internal docs table.');
    },
];
