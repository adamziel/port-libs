<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteIncrementalBlobIoPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream fkey2 blob column guard cites fkey2-5 section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'Test cases fkey2-5.* verify that the incremental blob API may not'));
        $t->true(is_string($source) && str_contains($source, 'write to a foreign key column while foreign-keys are enabled'));
    },
    'real upstream fkey2 blob column guard cites writable failure and readonly success' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'cannot open foreign key column for writing'));
        $t->true(is_string($source) && str_contains($source, 'incrblob -readonly t2 b 1'));
    },
];

for ($i = 1; $i <= 100; ++$i) {
    $payload = 'payload-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
    $rows = [
        ['rowid' => 1, 'setting_key' => 'parent-' . $i, 'parent_ref' => new SQLiteBlobValue('parent-' . $i)],
        ['rowid' => 2, 'setting_key' => 'child-' . $i, 'parent_ref' => new SQLiteBlobValue($payload)],
        ['rowid' => 3, 'setting_key' => 'payload-' . $i, 'payload_blob' => new SQLiteBlobValue(str_repeat('x', 8 + ($i % 5)))],
    ];

    $readonly = static fn (): array => SQLiteIncrementalBlobIoPlan::open($rows, [
        'table' => 'app_child_settings',
        'column' => 'parent_ref',
        'rowid' => 2,
        'readonly' => true,
        'foreign_key_columns' => ['parent_ref'],
    ]);
    $writablePayload = static fn (): array => SQLiteIncrementalBlobIoPlan::open($rows, [
        'table' => 'app_child_settings',
        'column' => 'payload_blob',
        'rowid' => 3,
        'foreign_key_columns' => ['parent_ref'],
    ]);
    $case = 'fkey2-5 incremental blob foreign key column guard dynamic ' . $i;

    foreach ([
        'status' => 'open',
        'database' => 'main',
        'table' => 'app_child_settings',
        'column' => 'parent_ref',
        'rowid' => 2,
        'readonly' => true,
        'bytes' => strlen($payload),
        'dependencies.0' => 'sqlite3-blob-open',
        'dependencies.1' => 'sqlite3-blob-bytes',
        'dependencies.2' => 'sqlite-fkey2-incremental-blob-foreign-key-column-guard',
    ] as $path => $expected) {
        $tests[$case . ' readonly foreign key column ' . $path] = static function (TestRunner $t) use ($readonly, $path, $expected, $value): void {
            $t->same($expected, $value($readonly(), (string) $path));
        };
    }
    $tests[$case . ' readonly foreign key column payload bytes'] = static function (TestRunner $t) use ($readonly, $payload): void {
        $t->same($payload, $readonly()['payload']->bytes);
    };

    foreach ([
        'status' => 'open',
        'table' => 'app_child_settings',
        'column' => 'payload_blob',
        'rowid' => 3,
        'readonly' => false,
        'bytes' => 8 + ($i % 5),
        'dependencies.2' => 'sqlite-fkey2-incremental-blob-foreign-key-column-guard',
    ] as $path => $expected) {
        $tests[$case . ' writable non foreign key column ' . $path] = static function (TestRunner $t) use ($writablePayload, $path, $expected, $value): void {
            $t->same($expected, $value($writablePayload(), (string) $path));
        };
    }

    $tests[$case . ' writable foreign key column is rejected before handle opens'] = static function (TestRunner $t) use ($rows): void {
        $t->throws(RuntimeException::class, static fn () => SQLiteIncrementalBlobIoPlan::open($rows, [
            'table' => 'app_child_settings',
            'column' => 'parent_ref',
            'rowid' => 2,
            'foreign_key_columns' => ['parent_ref'],
        ]));
    };
    $tests[$case . ' readonly foreign key column can be read'] = static function (TestRunner $t) use ($readonly, $payload): void {
        $read = SQLiteIncrementalBlobIoPlan::read($readonly(), 0, strlen($payload));
        $t->same($payload, $read['bytes']->bytes);
    };
    $tests[$case . ' writable payload column still writes fixed size blob'] = static function (TestRunner $t) use ($rows, $writablePayload): void {
        $opened = $writablePayload();
        $written = SQLiteIncrementalBlobIoPlan::write($rows, $opened, 0, 'DATA');
        $t->same('DATA', substr($written['payload']->bytes, 0, 4));
    };
}

return $tests;
