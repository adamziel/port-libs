# malformed-text-current-next70

## Behavior

Adds `SQLiteMalformedTextCurrentNextCursor`, a bounded native PHP current/next cursor for SQLite text comparisons over malformed `wp_options.option_name` bytes. It reuses SQLite storage-class comparison and BINARY/NOCASE/RTRIM collation semantics, reports malformed UTF-8 diagnostics without rejecting damaged bytes, supports seek/range scans, and keeps Application payload rows attached for import/repair previews.

This avoids accepted Unicode GLOB range and UTF-16 record-encoding guard work. It does not touch LIKE/GLOB matcher internals, VFS, WAL, B-tree, JSON, or suite admission surfaces.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedTextCurrentNext70Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-malformed-text-current-next70.php --self-test`
- PHP lint: changed PHP files only
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is required. The slice reuses existing native PHP comparison/storage-class primitives and standard `preg_match('//u')` only for diagnostics.

## Expected Dashboard Movement

Adds one new focused libsqlite test file with 45 PASS cases. `phpPass` is updated by +45 from 26014 to 26059. Mapped upstream coverage is unchanged because this is focused current/next behavior coverage, not a new upstream denominator unit.
