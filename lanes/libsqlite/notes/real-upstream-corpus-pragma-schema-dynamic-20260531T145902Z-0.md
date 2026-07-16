# real-upstream-corpus-pragma-schema-dynamic-20260531T145902Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-13.1`: `vdbe_trace`, `vdbe_listing`, and `sql_trace` are accepted as write-form PRAGMAs, return no rows, can be enabled around DDL/DML/blob SELECT work, then disabled without changing query results.

Patch:
- Added `SQLitePragmaTraceState` for trace PRAGMA state, empty write-result handling, enabled-mode reads, and trace-event capture that leaves result rows untouched.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrace20260531T145902ZTest.php` with 1002 focused TestRunner cases and 8507 behavior assertions across trace flag writes, DDL/DML logging, BLOB/REAL/NULL/integer SELECT preservation, and clean disable behavior.

Non-overlap:
- This owns only `pragma.test` `pragma-13.1`.
- It avoids existing accepted cache/default-cache/page-count/schema reload (`pragma-1`, `pragma-4`, `pragma-14`, `pragma-15`), store-mode (`pragma-17`, `pragma-18`), table-valued schema catalog, JSON table, pager/VFS, B-tree, WAL, and SELECT executor batches.

Dependency closure:
- No new external support component is needed. The behavior is modeled in native PHP lane source and verified by focused lane tests.

Verification:
- `php -l lanes/libsqlite/src/SQLitePragmaTraceState.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrace20260531T145902ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrace20260531T145902ZTest.php` passed: 1 test file, 8507 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 1 test file, 3 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` passed.
