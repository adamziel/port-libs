<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeySetDefaultRecursivePlan;

$tables = [
    'categories' => [
        ['id' => 0, 'name' => 'Uncategorized'],
        ['id' => 1, 'name' => 'Plugins'],
        ['id' => 2, 'name' => 'Themes'],
    ],
    'posts' => [
        ['id' => 10, 'category_id' => 1, 'title' => 'Plugin import'],
        ['id' => 11, 'category_id' => 1, 'title' => 'Plugin cleanup'],
        ['id' => 12, 'category_id' => 2, 'title' => 'Theme import'],
        ['id' => 13, 'category_id' => null, 'title' => 'Loose draft'],
    ],
    'postmeta' => [
        ['id' => 100, 'post_id' => 10, 'meta_key' => '_source'],
        ['id' => 101, 'post_id' => 11, 'meta_key' => '_source'],
        ['id' => 102, 'post_id' => 12, 'meta_key' => '_source'],
        ['id' => 103, 'post_id' => null, 'meta_key' => '_loose'],
    ],
];
$foreignKeys = [
    [
        'parent_table' => 'categories',
        'parent_key' => 'id',
        'child_table' => 'posts',
        'child_row_key' => 'id',
        'child_key' => 'category_id',
        'on_delete' => 'SET DEFAULT',
        'default' => 0,
    ],
    [
        'parent_table' => 'posts',
        'parent_key' => 'id',
        'child_table' => 'postmeta',
        'child_row_key' => 'id',
        'child_key' => 'post_id',
        'on_delete' => 'SET DEFAULT',
        'default' => 0,
    ],
];
$run = static fn (?array $deleteQueue = null, ?array $sourceTables = null, ?array $fks = null): array => SQLiteForeignKeySetDefaultRecursivePlan::apply(
    $sourceTables ?? $tables,
    $fks ?? $foreignKeys,
    $deleteQueue ?? [['table' => 'categories', 'key' => 1]],
);

$tests = [
    'foreign key set default recursive deletes requested category row' => static function (TestRunner $t) use ($run): void {
        $t->same([0, 2], array_column($run()['tables']['categories'], 'id'));
    },
    'foreign key set default recursive preserves other tables after category delete' => static function (TestRunner $t) use ($run): void {
        $t->same([10, 11, 12, 13], array_column($run()['tables']['posts'], 'id'));
    },
    'foreign key set default recursive rewrites first child row' => static function (TestRunner $t) use ($run): void {
        $t->same(0, $run()['tables']['posts'][0]['category_id']);
    },
    'foreign key set default recursive rewrites second child row' => static function (TestRunner $t) use ($run): void {
        $t->same(0, $run()['tables']['posts'][1]['category_id']);
    },
    'foreign key set default recursive preserves unrelated child reference' => static function (TestRunner $t) use ($run): void {
        $t->same(2, $run()['tables']['posts'][2]['category_id']);
    },
    'foreign key set default recursive preserves null child reference' => static function (TestRunner $t) use ($run): void {
        $t->same(null, $run()['tables']['posts'][3]['category_id']);
    },
    'foreign key set default recursive records parent delete action first' => static function (TestRunner $t) use ($run): void {
        $t->same('delete-parent', $run()['actions'][0]['action']);
    },
    'foreign key set default recursive records set default actions' => static function (TestRunner $t) use ($run): void {
        $t->same(['set-default-child', 'set-default-child'], array_column(array_slice($run()['actions'], 1), 'action'));
    },
    'foreign key set default recursive records default value per child' => static function (TestRunner $t) use ($run): void {
        $t->same([0, 0], array_column(array_slice($run()['actions'], 1), 'default'));
    },
    'foreign key set default recursive reports parent and child changes' => static function (TestRunner $t) use ($run): void {
        $t->same(3, $run()['changes']);
    },
    'foreign key set default recursive has no violation when default parent survives' => static function (TestRunner $t) use ($run): void {
        $t->same([], $run()['violations']);
    },
    'foreign key set default recursive missing delete row is no op' => static function (TestRunner $t) use ($run): void {
        $t->same(0, $run([['table' => 'categories', 'key' => 99]])['changes']);
    },
    'foreign key set default recursive missing delete row records no actions' => static function (TestRunner $t) use ($run): void {
        $t->same([], $run([['table' => 'categories', 'key' => 99]])['actions']);
    },
    'foreign key set default recursive can delete multiple parents sequentially' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'categories', 'key' => 1], ['table' => 'categories', 'key' => 2]]);
        $t->same([0, 0, 0, null], array_column($result['tables']['posts'], 'category_id'));
    },
    'foreign key set default recursive multiple parent deletes count all rewrites' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'categories', 'key' => 1], ['table' => 'categories', 'key' => 2]]);
        $t->same(5, $result['changes']);
    },
    'foreign key set default recursive reports missing default parent violations' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'categories', 'key' => 1]], [
            'categories' => [
                ['id' => 1, 'name' => 'Plugins'],
                ['id' => 2, 'name' => 'Themes'],
            ],
            'posts' => [
                ['id' => 10, 'category_id' => 1, 'title' => 'Plugin import'],
            ],
            'postmeta' => [],
        ]);
        $t->same(['missing-default-parent'], array_column($result['violations'], 'reason'));
    },
    'foreign key set default recursive records violation child key as default' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'categories', 'key' => 1]], [
            'categories' => [
                ['id' => 1, 'name' => 'Plugins'],
            ],
            'posts' => [
                ['id' => 10, 'category_id' => 1, 'title' => 'Plugin import'],
            ],
            'postmeta' => [],
        ]);
        $t->same([0], array_column($result['violations'], 'child_key'));
    },
    'foreign key set default recursive cascaded post delete rewrites postmeta default' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $result = $run([['table' => 'categories', 'key' => 1]], null, $fks);
        $t->same([0, 0, 12, null], array_column($result['tables']['postmeta'], 'post_id'));
    },
    'foreign key set default recursive cascaded post delete removes posts' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $result = $run([['table' => 'categories', 'key' => 1]], null, $fks);
        $t->same([12, 13], array_column($result['tables']['posts'], 'id'));
    },
    'foreign key set default recursive cascaded post delete records max depth' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $t->same(1, $run([['table' => 'categories', 'key' => 1]], null, $fks)['max_depth']);
    },
    'foreign key set default recursive cascaded post delete records queue actions' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $result = $run([['table' => 'categories', 'key' => 1]], null, $fks);
        $t->same(['queue-cascade-delete', 'queue-cascade-delete'], array_values(array_filter(array_column($result['actions'], 'action'), static fn ($action): bool => $action === 'queue-cascade-delete')));
    },
    'foreign key set default recursive cascaded post delete records child defaults' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $result = $run([['table' => 'categories', 'key' => 1]], null, $fks);
        $t->same([0, 0], array_column(array_values(array_filter($result['actions'], static fn (array $action): bool => $action['action'] === 'set-default-child')), 'default'));
    },
    'foreign key set default recursive cascaded post delete counts deletes and rewrites' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $t->same(5, $run([['table' => 'categories', 'key' => 1]], null, $fks)['changes']);
    },
    'foreign key set default recursive preserves defaulted rows after later unrelated delete' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'categories', 'key' => 1], ['table' => 'posts', 'key' => 12]]);
        $t->same([0, 0, null], array_column($result['tables']['posts'], 'category_id'));
    },
    'foreign key set default recursive direct post delete rewrites matching meta' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'posts', 'key' => 10]]);
        $t->same([0, 11, 12, null], array_column($result['tables']['postmeta'], 'post_id'));
    },
    'foreign key set default recursive direct post delete preserves categories' => static function (TestRunner $t) use ($run): void {
        $result = $run([['table' => 'posts', 'key' => 10]]);
        $t->same([0, 1, 2], array_column($result['tables']['categories'], 'id'));
    },
    'foreign key set default recursive direct post delete counts parent and meta rewrite' => static function (TestRunner $t) use ($run): void {
        $t->same(2, $run([['table' => 'posts', 'key' => 10]])['changes']);
    },
    'foreign key set default recursive rejects missing delete table' => static function (TestRunner $t) use ($run): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run([['table' => 'missing', 'key' => 1]]));
    },
    'foreign key set default recursive rejects malformed delete table' => static function (TestRunner $t) use ($run): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run([['table' => 'bad-table', 'key' => 1]]));
    },
    'foreign key set default recursive rejects malformed child key' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['child_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, null, $fks));
    },
    'foreign key set default recursive rejects unsupported action' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, null, $fks));
    },
    'foreign key set default recursive rejects missing parent table rows' => static function (TestRunner $t) use ($run): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, ['posts' => [['id' => 10, 'category_id' => 1]]]));
    },
    'foreign key set default recursive rejects missing child table rows' => static function (TestRunner $t) use ($run): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, ['categories' => [['id' => 1]]]));
    },
    'foreign key set default recursive rejects missing child row key column' => static function (TestRunner $t) use ($run): void {
        $source = [
            'categories' => [['id' => 1]],
            'posts' => [['category_id' => 1]],
        ];
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, $source));
    },
    'foreign key set default recursive rejects missing child fk column' => static function (TestRunner $t) use ($run): void {
        $source = [
            'categories' => [['id' => 1]],
            'posts' => [['id' => 10]],
        ];
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, $source));
    },
    'foreign key set default recursive supports string keys' => static function (TestRunner $t) use ($run, $foreignKeys): void {
        $source = [
            'categories' => [['id' => 'default'], ['id' => 'plugin']],
            'posts' => [['id' => 'p1', 'category_id' => 'plugin']],
            'postmeta' => [],
        ];
        $fks = $foreignKeys;
        $fks[0]['default'] = 'default';
        $t->same(['default'], array_column($run([['table' => 'categories', 'key' => 'plugin']], $source, $fks)['tables']['posts'], 'category_id'));
    },
    'foreign key set default recursive preserves table key order in result' => static function (TestRunner $t) use ($run): void {
        $t->same(['categories', 'postmeta', 'posts'], array_keys($run()['tables']));
    },
    'foreign key set default recursive exposes action source table' => static function (TestRunner $t) use ($run): void {
        $t->same(['categories', 'categories'], array_column(array_slice($run()['actions'], 1), 'from_table'));
    },
    'foreign key set default recursive exposes action source key' => static function (TestRunner $t) use ($run): void {
        $t->same([1, 1], array_column(array_slice($run()['actions'], 1), 'from_key'));
    },
    'foreign key set default recursive leaves max depth zero without cascaded delete' => static function (TestRunner $t) use ($run): void {
        $t->same(0, $run()['max_depth']);
    },
];

return $tests;
