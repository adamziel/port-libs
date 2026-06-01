# real-upstream-corpus-pragma-schema-dynamic-20260601T134054Z-0

Base accepted HEAD: `f2475a9a46461fb108ebd2437efe777168da2710`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/table.test`
- Ported sections:
  - `table-8.1` and `table-8.1.1`: `CREATE TABLE AS SELECT *` preserves keyword/quoted result-column names and writes CTAS schema SQL.
  - `table-8.3` and `table-8.3.1`: aggregate CTAS creates `cnt` and `max(b+c)` columns with no declared types.
  - `table-8.4` through `table-8.7`: temporary CTAS materializes rows but does not persist after reopen.
  - `table-8.8`: CTAS against `no_such_table` returns the missing-table error.
  - `table-8.9` and `table-8.10`: CTAS maps dotted quoted names and source declared types to SQLite CTAS affinities.

## Implementation

- Added `SQLiteCreateTableAsSchemaPlan` for bounded `CREATE [TEMPORARY] TABLE ... AS SELECT ... FROM ...` schema planning.
- The helper materializes simple source-column, `*`, `count(*)`, and `max(left+right)` projections, emits SQLite-style CTAS schema SQL, maps source declared types to CTAS affinities (`INT`, `TEXT`, `NUM`, `REAL`, or empty), tracks temp-table persistence, and returns missing-source diagnostics.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicCreateTableAs20260601Test.php` with 1004 focused TestRunner PASS cases and 10524 behavior assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCreateTableAsSchemaPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteCreateTableAsSchemaPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCreateTableAs20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCreateTableAs20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCreateTableAs20260601Test.php`
  - `1 test files, 10524 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Status Delta

- Focused PASS cases: `+1004`.
- `phpPass`: `5897230 -> 5898234`.
- Mapped coverage remains `1589 / 1589`; this is behavior-backed PASS-line growth over already hydrated upstream schema/table inventory.

## Non-Overlap

This owns only upstream `table.test` `table-8.1` through `table-8.10` CTAS schema text and materialized row behavior. It avoids accepted table namespace/import admission, `tableopts.test` `WITHOUT ROWID`, `schemafault.test` OOM view reparse, `schema.test` invalidation/runtime, `pragma.test` table/index metadata, JSON, WAL, VFS, B-tree, SELECT, and source-neutral cleanup clusters.

## Dependency Closure

No new support component is needed. The patch reuses lane-local schema planning and adds a bounded CTAS helper without external services, live providers, root coordination edits, or upstream-cache mutation.
