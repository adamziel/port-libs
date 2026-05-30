<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;

$tests = [];

$version = static fn (array $result): int => $result['value'];
$row = static fn (array $result, string $name): int => $result['rows'][0][$name];
$header = static fn (array $result, string $name): int => $result['header'][$name];

$state = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
    'main' => ['schema_version' => 7, 'data_version' => 3, 'change_counter' => 3],
    'temp' => ['schema_version' => 2, 'data_version' => 1, 'change_counter' => 1],
]);

$cases = [
    'schema version query status' => [static fn (): mixed => $state()->execute('PRAGMA schema_version')['status'], 'ok'],
    'schema version query pragma name' => [static fn (): mixed => $state()->execute('PRAGMA schema_version')['pragma'], 'schema_version'],
    'schema version query default schema' => [static fn (): mixed => $state()->execute('PRAGMA schema_version')['schema'], 'main'],
    'schema version query current value' => [static fn (): mixed => $version($state()->execute('PRAGMA schema_version')), 7],
    'schema version query row shape' => [static fn (): mixed => $row($state()->execute('PRAGMA schema_version'), 'schema_version'), 7],
    'schema version query header cookie' => [static fn (): mixed => $header($state()->execute('PRAGMA schema_version'), 'schema_cookie'), 7],
    'schema version query header change counter' => [static fn (): mixed => $header($state()->execute('PRAGMA schema_version'), 'file_change_counter'), 3],
    'data version query status' => [static fn (): mixed => $state()->execute('PRAGMA data_version')['status'], 'ok'],
    'data version query pragma name' => [static fn (): mixed => $state()->execute('PRAGMA data_version')['pragma'], 'data_version'],
    'data version query current value' => [static fn (): mixed => $version($state()->execute('PRAGMA data_version')), 3],
    'data version query row shape' => [static fn (): mixed => $row($state()->execute('PRAGMA data_version'), 'data_version'), 3],
    'data version query header cookie remains schema version' => [static fn (): mixed => $header($state()->execute('PRAGMA data_version'), 'schema_cookie'), 7],
    'data version query header change counter' => [static fn (): mixed => $header($state()->execute('PRAGMA data_version'), 'file_change_counter'), 3],
    'schema version hyphenless uppercase accepted' => [static fn (): mixed => $version($state()->execute(' pragma SCHEMA_VERSION ; ')), 7],
    'data version uppercase accepted' => [static fn (): mixed => $version($state()->execute('PRAGMA DATA_VERSION')), 3],
    'schema-qualified schema version reads temp' => [static fn (): mixed => $version($state()->execute('PRAGMA temp.schema_version')), 2],
    'schema-qualified data version reads temp' => [static fn (): mixed => $version($state()->execute('PRAGMA temp.data_version')), 1],
    'missing attached schema starts schema version zero' => [static fn (): mixed => $version($state()->execute('PRAGMA archive.schema_version')), 0],
    'missing attached schema starts data version one' => [static fn (): mixed => $version($state()->execute('PRAGMA archive.data_version')), 1],
    'schema version assignment returns assigned reason' => [static fn (): mixed => $state()->execute('PRAGMA schema_version=11')['reason'], 'assigned'],
    'schema version assignment reports changed' => [static fn (): mixed => $state()->execute('PRAGMA schema_version=11')['changed'], true],
    'schema version assignment updates current row' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=11');
        return $version($s->execute('PRAGMA schema_version'));
    }, 11],
    'schema version assignment updates header cookie' => [static function () use ($state, $header): mixed {
        $s = $state();
        return $header($s->execute('PRAGMA schema_version=11'), 'schema_cookie');
    }, 11],
    'schema version assignment preserves data version' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=11');
        return $version($s->execute('PRAGMA data_version'));
    }, 3],
    'schema version repeated assignment is unchanged' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 11]]))->execute('PRAGMA schema_version=11')['changed'], false],
    'schema version parenthesized assignment' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion())->execute('PRAGMA schema_version(19)')), 19],
    'schema version plus sign assignment' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion())->execute('PRAGMA schema_version=+23')), 23],
    'schema version schema assignment isolated to temp' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA temp.schema_version=9');
        return [$version($s->execute('PRAGMA temp.schema_version')), $version($s->execute('PRAGMA main.schema_version'))];
    }, [9, 7]],
    'data version assignment is ignored reason' => [static fn (): mixed => $state()->execute('PRAGMA data_version=9')['reason'], 'read_only_pragma_ignored'],
    'data version assignment reports unchanged' => [static fn (): mixed => $state()->execute('PRAGMA data_version=9')['changed'], false],
    'data version assignment preserves value' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->execute('PRAGMA data_version=9');
        return $version($s->execute('PRAGMA data_version'));
    }, 3],
    'data version parenthesized assignment ignored' => [static fn (): mixed => $version($state()->execute('PRAGMA data_version(9)')), 3],
    'data version bump reports reason' => [static fn (): mixed => $state()->bumpDataVersion('main', 1, 'writer_commit')['reason'], 'writer_commit'],
    'data version bump increments current value' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->bumpDataVersion();
        return $version($s->execute('PRAGMA data_version'));
    }, 4],
    'data version bump by multiple increments header counter' => [static function () use ($state, $header): mixed {
        $s = $state();
        return $header($s->bumpDataVersion('main', 4), 'file_change_counter');
    }, 7],
    'data version bump preserves schema cookie' => [static function () use ($state, $header): mixed {
        $s = $state();
        return $header($s->bumpDataVersion('main'), 'schema_cookie');
    }, 7],
    'data version bump isolated by schema' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->bumpDataVersion('temp', 2);
        return [$version($s->execute('PRAGMA temp.data_version')), $version($s->execute('PRAGMA main.data_version'))];
    }, [3, 3]],
    'data version bump creates attached schema' => [static function () use ($state, $version): mixed {
        $s = $state();
        $s->bumpDataVersion('network', 2);
        return $version($s->execute('PRAGMA network.data_version'));
    }, 3],
    'header update exposes schema cookie and change counter' => [static fn (): mixed => $state()->headerUpdate('main'), ['schema_cookie' => 7, 'file_change_counter' => 3]],
    'state exposes dirty flags after schema assignment' => [static function () use ($state): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=12');
        return $s->state()['main']['schema_dirty'];
    }, true],
    'state exposes dirty flags after data bump' => [static function () use ($state): mixed {
        $s = $state();
        $s->bumpDataVersion();
        return $s->state()['main']['data_dirty'];
    }, true],
    'parse schema version assignment' => [static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA main.schema_version=17'), ['pragma' => 'schema_version', 'schema' => 'main', 'value' => 17]],
    'parse data version query' => [static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA data_version'), ['pragma' => 'data_version', 'schema' => 'main', 'value' => null]],
    'parse attached data version parenthesized' => [static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA aux.data_version(22)'), ['pragma' => 'data_version', 'schema' => 'aux', 'value' => 22]],
    'parse schema version trailing semicolon' => [static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA schema_version=18;'), ['pragma' => 'schema_version', 'schema' => 'main', 'value' => 18]],
    'constructor lowercases schema names' => [static fn (): mixed => array_key_first((new SQLitePragmaSchemaDataVersion(['Network' => ['schema_version' => 4]]))->state()), 'network'],
    'constructor defaults change counter from data version' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion(['main' => ['data_version' => 8]]))->headerUpdate('main')['file_change_counter'], 8],
    'constructor defaults data version from change counter' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion(['main' => ['change_counter' => 9]]))->execute('PRAGMA data_version')), 9],
    'default state has main schema version zero' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion())->execute('PRAGMA schema_version')), 0],
    'default state has main data version one' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion())->execute('PRAGMA data_version')), 1],
    'schema assignment can set zero' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 5]]))->execute('PRAGMA schema_version=0')), 0],
    'schema assignment max signed integer accepted' => [static fn (): mixed => $version((new SQLitePragmaSchemaDataVersion())->execute('PRAGMA schema_version=2147483647')), 2147483647],
    'data version bump by multiple reports changed' => [static fn (): mixed => $state()->bumpDataVersion('main', 3)['changed'], true],
    'data version bump row includes new data version' => [static fn (): mixed => $row($state()->bumpDataVersion('main', 3), 'data_version'), 6],
    'data version ignored assignment keeps current reason after read' => [static function () use ($state): mixed {
        $s = $state();
        $s->execute('PRAGMA data_version=9');
        return $s->execute('PRAGMA data_version')['reason'];
    }, 'current'],
    'schema version assignment then data bump keeps both headers' => [static function () use ($state): mixed {
        $s = $state();
        $s->execute('PRAGMA schema_version=15');
        $s->bumpDataVersion('main', 2);
        return $s->headerUpdate('main');
    }, ['schema_cookie' => 15, 'file_change_counter' => 5]],
    'from snapshot seeds schema version' => [static function () use ($version): mixed {
        $page = str_pad("SQLite format 3\0" . pack('nCCCCCCCN', 1024, 1, 1, 0, 64, 32, 32, 0, 5), 100, "\0");
        $page = substr_replace($page, pack('N', 41), 24, 4);
        $page = substr_replace($page, pack('N', 13), 40, 4);
        return $version(SQLitePragmaSchemaDataVersion::fromSnapshot(SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($page . str_repeat("\0", 1024 * 4))))->execute('PRAGMA schema_version'));
    }, 13],
    'from snapshot seeds data version' => [static function () use ($version): mixed {
        $page = str_pad("SQLite format 3\0" . pack('nCCCCCCCN', 1024, 1, 1, 0, 64, 32, 32, 0, 5), 100, "\0");
        $page = substr_replace($page, pack('N', 41), 24, 4);
        $page = substr_replace($page, pack('N', 13), 40, 4);
        return $version(SQLitePragmaSchemaDataVersion::fromSnapshot(SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($page . str_repeat("\0", 1024 * 4))))->execute('PRAGMA data_version'));
    }, 41],
    'negative schema assignment rejected' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->execute('PRAGMA schema_version=-1')), null],
    'overflow schema assignment rejected' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->execute('PRAGMA schema_version=2147483648')), null],
    'invalid data version bump rejected' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => $state()->bumpDataVersion('main', 0)), null],
    'quoted schema rejected' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaSchemaDataVersion::parse('PRAGMA "main".schema_version')), null],
    'parse user version query' => [static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA user_version'), ['pragma' => 'user_version', 'schema' => 'main', 'value' => null]],
    'empty schema name rejected by constructor' => [static fn (TestRunner $t): mixed => $t->throws(InvalidArgumentException::class, static fn () => new SQLitePragmaSchemaDataVersion(['' => []])), null],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pragma schema data version current next25 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if ($expected === null) {
            $callback($t);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
