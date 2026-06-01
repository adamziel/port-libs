# real-upstream-corpus-pragma-schema-dynamic-20260601T230007Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-3.5.2`: quoted `PRAGMA integrity_check='4'` is a target-name lookup and returns `no such table: 4`.
- `pragma-3.6`: bare non-numeric `PRAGMA integrity_check=xyz` is a target-name lookup.
- `pragma-3.6b`, `pragma-3.6c`, `pragma-3.9b`, and `pragma-3.9c`: equals-form targets route to an attached schema or `sqlite_schema`.
- `pragma-3.7` and `pragma-3.10` through `pragma-3.18`: numeric equals/paren arguments limit rows across attached-schema integrity sweeps, while zero restores the default limit.

Implemented:

- Added `SQLitePragmaIntegrityTargetArgument`, a generic integrity/quick_check PRAGMA argument router for equals/paren numeric limits, quoted/bare target names, attached schema target dispatch, `sqlite_schema` target success, missing-target catchsql errors, and zero-limit defaulting.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicIntegrityTarget20260601Test.php` with 1,002 focused TestRunner PASS cases and 9,759 assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityTargetArgument.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIntegrityTarget20260601Test.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIntegrityTarget20260601Test.php` -> `1 test files, 9759 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 9 assertions, 0 failures`.
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status.json valid\n";'` -> `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite` -> passed.

Counter movement:

- `phpPass`: `6287267 -> 6288269` (`+1002` focused TestRunner PASS cases).
- Mapped coverage remains `1589 / 1589`.

Non-overlap:

This owns only upstream `pragma.test` `pragma-3.5.2` through `pragma-3.18` integrity-check equals-form target/limit routing. It avoids accepted writable-schema integrity checks (`pragma-3.20..3.25`), index root swaps (`pragma-3.40..3.41`), attached schema qualification (`pragma-22.*`), malformed leaf integrity (`pragma-24.*`), generated/temp integrity (`pragma-25.0`), file-control, freelist-count, table-valued PRAGMA, VFS, WAL, B-tree, JSON, SELECT, and source-neutral cleanup clusters.

Dependency closure:

No new support component is required. The slice is lane-local PRAGMA parser/routing behavior and reuses existing TestRunner/autoload infrastructure.
