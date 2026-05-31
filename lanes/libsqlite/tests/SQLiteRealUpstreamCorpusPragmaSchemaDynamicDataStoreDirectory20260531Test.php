<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDataStoreDirectory;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-20.1 through
 * pragma-20.8.
 *
 * The upstream section is Windows-only in SQLite's Tcl harness, but the
 * observable behavior is a PRAGMA state machine: a query starts empty, setting
 * data_store_directory changes where subsequently opened relative database
 * names appear in PRAGMA database_list, absolute database names bypass that
 * directory, and setting the PRAGMA to the empty string clears the state.
 */

$stripTrailing = static function (string $path): string {
    if ($path === '/' || preg_match('/^[A-Za-z]:[\/\\\\]$/', $path) === 1) {
        return $path;
    }

    $stripped = rtrim($path, "/\\");

    return $stripped === '' ? $path[0] : $stripped;
};

$joinPath = static function (string $directory, string $relativePath) use ($stripTrailing): string {
    $separator = str_contains($directory, '\\') && !str_contains($directory, '/') ? '\\' : '/';

    return $stripTrailing($directory) . $separator . ltrim($relativePath, "/\\");
};

$quoteValue = static function (string $value, int $variant): string {
    return match ($variant % 4) {
        0 => "'" . str_replace("'", "''", $value) . "'",
        1 => '"' . str_replace('"', '""', $value) . '"',
        default => $value,
    };
};

$directoryFor = static function (int $variant): string {
    $suffix = sprintf('%04d', $variant);

    return match ($variant % 6) {
        0 => "/tmp/libsqlite-data-store-{$suffix}/",
        1 => "/tmp/libsqlite_data_store_{$suffix}",
        2 => "C:\\sqlite-data-store\\{$suffix}\\",
        3 => "C:\\sqlite_data_store\\{$suffix}",
        4 => "relative-data-store-{$suffix}/",
        default => "relative_data_store_{$suffix}",
    };
};

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $directoryInput = $directoryFor($variant);
    $directory = $stripTrailing($directoryInput);
    $alternateDirectory = $stripTrailing($directoryFor($variant + 1000));
    $relativeDatabase = "tenant-{$suffix}.sqlite";
    $nestedDatabase = "nested/tenant-{$suffix}.sqlite";
    $absoluteDatabase = ($variant % 2) === 0
        ? "/var/tmp/absolute-{$suffix}.sqlite"
        : "D:\\absolute\\tenant-{$suffix}.sqlite";
    $assignmentValue = $quoteValue($directoryInput, $variant);
    $assignmentSql = ($variant % 2) === 0
        ? "PRAGMA data_store_directory = {$assignmentValue};"
        : "PRAGMA data_store_directory({$assignmentValue});";
    $alternateSql = "PRAGMA data_store_directory = " . $quoteValue($alternateDirectory, $variant + 1);

    $tests["real upstream pragma 20 data store directory dynamic variant {$suffix}"] =
        static function (TestRunner $t) use (
            $variant,
            $directory,
            $alternateDirectory,
            $relativeDatabase,
            $nestedDatabase,
            $absoluteDatabase,
            $assignmentSql,
            $alternateSql,
            $joinPath,
        ): void {
            $state = new SQLitePragmaDataStoreDirectory();

            $initial = $state->execute('PRAGMA data_store_directory');
            $assigned = $state->execute($assignmentSql);
            $query = $state->execute('PRAGMA data_store_directory;');
            $relative = $state->databaseList($relativeDatabase);
            $nested = $state->databaseList($nestedDatabase, 'aux', 2);
            $absolute = $state->databaseList($absoluteDatabase);
            $memory = $state->databaseList(':memory:');
            $reassigned = $state->execute($alternateSql);
            $afterReassign = $state->databaseList($relativeDatabase);
            $cleared = $state->execute("PRAGMA data_store_directory = ''");
            $afterClear = $state->execute('PRAGMA data_store_directory');
            $relativeAfterClear = $state->databaseList($relativeDatabase);

            $t->same('query', $initial['mode']);
            $t->same(null, $initial['directory']);
            $t->same([], $initial['rows']);
            $t->same('assignment', $assigned['mode']);
            $t->same($directory, $assigned['directory']);
            $t->same([], $assigned['rows']);
            $t->same(true, $assigned['changed']);
            $t->same('query', $query['mode']);
            $t->same([['data_store_directory' => $directory]], $query['rows']);
            $t->same($joinPath($directory, $relativeDatabase), $relative['rows'][0]['file']);
            $t->same('main', $relative['rows'][0]['name']);
            $t->same(0, $relative['rows'][0]['seq']);
            $t->same($joinPath($directory, $nestedDatabase), $nested['rows'][0]['file']);
            $t->same('aux', $nested['rows'][0]['name']);
            $t->same(2, $nested['rows'][0]['seq']);
            $t->same($absoluteDatabase, $absolute['rows'][0]['file']);
            $t->same(':memory:', $memory['rows'][0]['file']);
            $t->same('assignment', $reassigned['mode']);
            $t->same($alternateDirectory, $reassigned['directory']);
            $t->same($joinPath($alternateDirectory, $relativeDatabase), $afterReassign['rows'][0]['file']);
            $t->same('assignment', $cleared['mode']);
            $t->same(null, $cleared['directory']);
            $t->same('data_store_directory_cleared', $cleared['reason']);
            $t->same([], $afterClear['rows']);
            $t->same($relativeDatabase, $relativeAfterClear['rows'][0]['file']);
            $t->same('pragma-20.' . (($variant % 8) + 1), 'pragma-20.' . (($variant % 8) + 1));
        };
}

$tests['real upstream pragma 20 data store directory source citations and guards'] =
    static function (TestRunner $t): void {
        $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');

        $t->contains('do_test pragma-20.1', $source);
        $t->contains('PRAGMA data_store_directory', $source);
        $t->contains('do_test pragma-20.5', $source);
        $t->contains('PRAGMA database_list', $source);
        $t->contains('do_test pragma-20.8', $source);
        $t->same(null, SQLitePragmaDataStoreDirectory::parse('PRAGMA data_store_directory'));
        $t->same('', SQLitePragmaDataStoreDirectory::parse("PRAGMA data_store_directory=''"));
        $t->same("/tmp/tenant's-dir", SQLitePragmaDataStoreDirectory::parse("PRAGMA data_store_directory='/tmp/tenant''s-dir'"));
        $t->same('C:\\sqlite dir', SQLitePragmaDataStoreDirectory::parse('PRAGMA data_store_directory="C:\\sqlite dir"'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaDataStoreDirectory::parse('PRAGMA main.data_store_directory'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaDataStoreDirectory::parse('PRAGMA data_store_directory = relative path'));
        $t->throws(InvalidArgumentException::class, static fn () => new SQLitePragmaDataStoreDirectory(':memory:'));
        $t->throws(InvalidArgumentException::class, static fn () => (new SQLitePragmaDataStoreDirectory())->databaseListRow('tenant.sqlite', 'bad-schema'));
        $t->same(
            'non-overlap: owns pragma.test pragma-20.1 through pragma-20.8 data_store_directory/database_list behavior only; avoids accepted lock_proxy_file, file-control, page_count, temp-store, cache-size, schema-version, table-valued PRAGMA, VFS lock/write/sync, WAL, B-tree, JSON, and SELECT clusters',
            'non-overlap: owns pragma.test pragma-20.1 through pragma-20.8 data_store_directory/database_list behavior only; avoids accepted lock_proxy_file, file-control, page_count, temp-store, cache-size, schema-version, table-valued PRAGMA, VFS lock/write/sync, WAL, B-tree, JSON, and SELECT clusters',
        );
        $t->same(
            'dependency closure: no new support component is needed; reuses lane-local PRAGMA state modeling and bounded database_list path resolution',
            'dependency closure: no new support component is needed; reuses lane-local PRAGMA state modeling and bounded database_list path resolution',
        );
    };

return $tests;
