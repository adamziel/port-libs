<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

$version = static fn (array $result): int => $result['value'];
$row = static fn (array $result, string $name): int => $result['rows'][0][$name];
$header = static fn (array $result, string $name): int => $result['header'][$name];
$state = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
    'main' => ['schema_version' => 34, 'data_version' => 10, 'change_counter' => 10],
    'temp' => ['schema_version' => 4, 'data_version' => 2, 'change_counter' => 2],
    'archive' => ['schema_version' => 9, 'data_version' => 7, 'change_counter' => 7],
]);

$cases = [
    'local commit reports local reason' => [static fn (): mixed => $state()->recordLocalCommit('main', 1, 'same_connection_insert')['reason'], 'same_connection_insert'],
    'local commit leaves pragma data version stable' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordLocalCommit('main', 3);
        return $version($s->execute('PRAGMA data_version'));
    }, 10],
    'local commit advances file change counter' => [static fn (): mixed => $header($state()->recordLocalCommit('main', 3), 'file_change_counter'), 13],
    'local commit preserves schema cookie' => [static fn (): mixed => $header($state()->recordLocalCommit('main', 3), 'schema_cookie'), 34],
    'local commit row returns unchanged data version' => [static fn (): mixed => $row($state()->recordLocalCommit('main', 3), 'data_version'), 10],
    'local commit changed flag is false for data version' => [static fn (): mixed => $state()->recordLocalCommit('main', 3)['changed'], false],
    'local commit marks data dirty' => [static function () use ($state): mixed {
        $s = $state();
        $s->recordLocalCommit('main');
        return $s->state()['main']['data_dirty'];
    }, true],
    'local commit isolates temp schema' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordLocalCommit('temp', 5);
        return [$version($s->execute('PRAGMA temp.data_version')), $version($s->execute('PRAGMA main.data_version'))];
    }, [2, 10]],
    'local commit creates attached schema without bumping data version' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordLocalCommit('network', 2);
        return [$version($s->execute('PRAGMA network.data_version')), $s->headerUpdate('network')['file_change_counter']];
    }, [1, 3]],
    'external commit reports external reason' => [static fn (): mixed => $state()->recordExternalCommit('main', 2, 'other_connection_commit')['reason'], 'other_connection_commit'],
    'external commit bumps pragma data version' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordExternalCommit('main', 2);
        return $version($s->execute('PRAGMA data_version'));
    }, 12],
    'external commit bumps header change counter' => [static fn (): mixed => $header($state()->recordExternalCommit('main', 2), 'file_change_counter'), 12],
    'external commit row has new data version' => [static fn (): mixed => $row($state()->recordExternalCommit('main', 2), 'data_version'), 12],
    'external commit changed flag is true' => [static fn (): mixed => $state()->recordExternalCommit('main', 2)['changed'], true],
    'external commit preserves schema cookie' => [static fn (): mixed => $header($state()->recordExternalCommit('main', 2), 'schema_cookie'), 34],
    'external commit isolated to archive schema' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordExternalCommit('archive', 4);
        return [$version($s->execute('PRAGMA archive.data_version')), $version($s->execute('PRAGMA main.data_version'))];
    }, [11, 10]],
    'external commit creates attached schema at default plus bump' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordExternalCommit('analytics', 5);
        return [$version($s->execute('PRAGMA analytics.data_version')), $s->headerUpdate('analytics')['file_change_counter']];
    }, [6, 6]],
    'bump data version remains external commit alias' => [static fn (): mixed => $version($state()->bumpDataVersion('main', 2)), 12],
    'schema change reports schema reason' => [static fn (): mixed => $state()->recordSchemaChange('main', 1, 'create_table')['reason'], 'create_table'],
    'schema change increments schema version' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordSchemaChange('main', 2);
        return $version($s->execute('PRAGMA schema_version'));
    }, 36],
    'schema change increments header change counter' => [static fn (): mixed => $header($state()->recordSchemaChange('main', 2), 'file_change_counter'), 12],
    'schema change preserves same connection data version' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordSchemaChange('main', 2);
        return $version($s->execute('PRAGMA data_version'));
    }, 10],
    'schema change row has schema version' => [static fn (): mixed => $row($state()->recordSchemaChange('main', 2), 'schema_version'), 36],
    'schema change changed flag is true' => [static fn (): mixed => $state()->recordSchemaChange('main', 1)['changed'], true],
    'schema change marks schema dirty' => [static function () use ($state): mixed {
        $s = $state();
        $s->recordSchemaChange('main');
        return $s->state()['main']['schema_dirty'];
    }, true],
    'schema change isolates temp schema cookie' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordSchemaChange('temp', 3);
        return [$version($s->execute('PRAGMA temp.schema_version')), $version($s->execute('PRAGMA main.schema_version'))];
    }, [7, 34]],
    'schema change creates attached schema from zero' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordSchemaChange('scratch', 3);
        return [$version($s->execute('PRAGMA scratch.schema_version')), $s->headerUpdate('scratch')['file_change_counter']];
    }, [3, 4]],
    'schema assignment still preserves file change counter' => [static fn (): mixed => $header($state()->execute('PRAGMA schema_version=39'), 'file_change_counter'), 10],
    'schema assignment followed by local commit has assigned cookie and advanced counter' => [static function () use ($state): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=39');
        $s->recordLocalCommit('main', 2);
        return $s->headerUpdate('main');
    }, ['schema_cookie' => 39, 'file_change_counter' => 12]],
    'schema assignment followed by external commit bumps data only' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=39');
        $s->recordExternalCommit('main', 2);
        return [$version($s->execute('PRAGMA schema_version')), $version($s->execute('PRAGMA data_version'))];
    }, [39, 12]],
    'data version write ignored before external commit' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA data_version=99');
        $s->recordExternalCommit('main');
        return $version($s->execute('PRAGMA data_version'));
    }, 11],
    'observe header reports unchanged when counter matches' => [static fn (): mixed => $state()->observeHeader('main', 34, 10)['changed'], false],
    'observe header keeps data version when counter matches' => [static fn (): mixed => $version($state()->observeHeader('main', 34, 10)), 10],
    'observe header updates schema cookie' => [static function () use ($state): mixed {
        $s = $state();
        $s->observeHeader('main', 40, 10);
        return $s->headerUpdate('main');
    }, ['schema_cookie' => 40, 'file_change_counter' => 10]],
    'observe header bumps data version when change counter differs' => [static fn (): mixed => $version($state()->observeHeader('main', 34, 14)), 14],
    'observe header changed flag when counter differs' => [static fn (): mixed => $state()->observeHeader('main', 34, 14)['changed'], true],
    'observe header row mirrors data version' => [static fn (): mixed => $row($state()->observeHeader('main', 34, 14), 'data_version'), 14],
    'observe header reason is retained' => [static fn (): mixed => $state()->observeHeader('main', 34, 14, 'reload_header')['reason'], 'reload_header'],
    'observe header stores both header values' => [static function () use ($state): mixed {
        $s = $state();
        $s->observeHeader('archive', 15, 22);
        return $s->headerUpdate('archive');
    }, ['schema_cookie' => 15, 'file_change_counter' => 22]],
    'observe header creates attached schema' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->observeHeader('mirror', 5, 8);
        return [$version($s->execute('PRAGMA mirror.schema_version')), $version($s->execute('PRAGMA mirror.data_version'))];
    }, [5, 8]],
    'observe header marks data dirty on changed counter' => [static function () use ($state): mixed {
        $s = $state();
        $s->observeHeader('main', 34, 14);
        return $s->state()['main']['data_dirty'];
    }, true],
    'mixed local external sequence follows SQLite data version visibility' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordLocalCommit('main', 4);
        $afterLocal = $version($s->execute('PRAGMA data_version'));
        $s->recordExternalCommit('main', 2);
        return [$afterLocal, $version($s->execute('PRAGMA data_version')), $s->headerUpdate('main')['file_change_counter']];
    }, [10, 12, 16]],
    'mixed schema local external sequence preserves latest schema cookie' => [static function () use ($state): mixed {
        $s = $state();
        $s->recordSchemaChange('main', 1);
        $s->recordLocalCommit('main', 2);
        $s->recordExternalCommit('main', 3);
        return $s->headerUpdate('main');
    }, ['schema_cookie' => 35, 'file_change_counter' => 16]],
    'temp local commit followed by main external commit keeps temp data visible' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->recordLocalCommit('temp', 5);
        $s->recordExternalCommit('main', 1);
        return [$version($s->execute('PRAGMA temp.data_version')), $version($s->execute('PRAGMA main.data_version'))];
    }, [2, 11]],
    'archive observed header does not affect main header' => [static function () use ($state): mixed {
        $s = $state();
        $s->observeHeader('archive', 12, 21);
        return $s->headerUpdate('main');
    }, ['schema_cookie' => 34, 'file_change_counter' => 10]],
    'local commit rejects zero bump' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->recordLocalCommit('main', 0)), null],
    'schema change rejects zero bump' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->recordSchemaChange('main', 0)), null],
    'schema change rejects overflow target' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => (new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 2147483646]]))->recordSchemaChange('main', 2)), null],
    'observe header rejects negative schema version' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->observeHeader('main', -1, 10)), null],
    'observe header rejects negative change counter' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->observeHeader('main', 34, -1)), null],
    'observe header rejects overflow change counter' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->observeHeader('main', 34, 2147483648)), null],
    'local commit rejects invalid schema name' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->recordLocalCommit('bad-name')), null],
    'external commit rejects invalid schema name' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->recordExternalCommit('bad-name')), null],
    'schema change rejects invalid schema name' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->recordSchemaChange('bad-name')), null],
    'observe header rejects invalid schema name' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->observeHeader('bad-name', 1, 1)), null],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pragma schema version data current next34 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if ($expected === null) {
            $callback($t);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
