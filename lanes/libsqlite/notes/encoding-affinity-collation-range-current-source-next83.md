# Encoding affinity collation range current-source next83

Implemented `SQLiteAffinityRangeCurrentSourceCursor` for current/next range
scans over copied SQLite rows where lower/upper bounds are compared using
SQLite affinity and BINARY/NOCASE/RTRIM collation rules.

The slice covers mixed numeric text/integer/real storage, NULL exclusion from
range predicates, BLOB ordering outside text ranges, NOCASE peer grouping,
RTRIM space-only boundary behavior, and Application `wp_options` option-name /
option-value range scans without `ext/sqlite`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAffinityRangeCurrentSourceCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteAffinityCollationRangeCurrentSourceNext83Test.php`
- `php -l lanes/libsqlite/examples/application-affinity-collation-range-current-source-next83.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAffinityCollationRangeCurrentSourceNext83Test.php`
- `php lanes/libsqlite/examples/application-affinity-collation-range-current-source-next83.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses native
`SQLiteAffinityComparison`, `SQLiteBlobValue`, and existing lane autoloading.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed guards,
LIKE/GLOB cursor ranges, RTRIM comparison-only coverage, VDBE index cursor
affinity/collation behavior, JSON table cursor/source/constraint work, SQL
expression ORDER BY/subquery/GROUP BY text execution, B-tree/WAL/VFS accepted
clusters, and suite-runner evidence. The new surface is current-source range
yield behavior where the range bounds themselves apply SQLite affinity and
collation rules to copied row values.
