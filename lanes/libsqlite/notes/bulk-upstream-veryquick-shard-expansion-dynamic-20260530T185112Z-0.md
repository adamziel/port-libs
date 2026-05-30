# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T185112Z-0

Base accepted HEAD: `0eff666a68d9fc5c2de0693a82870643615fd7c5`

## Scope

Added `SQLiteRealUpstreamVeryquickBulkSelectShardTest.php`, a focused real-upstream veryquick SELECT shard that cites and ports behavior from hydrated upstream SQLite test files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where.test`

The PHP shard exercises distinct `SQLiteSelectSql::execute()` behavior over a generic `t1` application table: equality predicates, commuted range predicates, `BETWEEN`, text equality, modulo residual predicates, `OR` predicates, multi-column `ORDER BY`, mixed `ASC`/`DESC` ordering, `LIMIT`, and `OFFSET`.

## Counts

- Before focused PASS lines in this file: `0`
- After focused PASS lines in this file: `1289`
- PASS-line delta: `+1289`
- Behavior assertions: `7734`
- Mapped denominator rows: unchanged, `1472 / 1589`
- Upstream runner pass/fail rows: not changed; this is PHP behavior coverage, not runner-map admission.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVeryquickBulkSelectShardTest.php`
  - Result: `1 test files, 7734 assertions, 0 failures`
  - PASS lines: `1289`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVeryquickBulkSelectShardTest.php`
  - Result: no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Result: not present in this worktree (`Focused path does not exist...`)

## Non-Overlap

This slice does not add suite-evidence metadata rows and does not fabricate upstream script IDs. It avoids stale `next965-980` runner rows and adds a fresh behavior-backed bulk shard against real upstream veryquick SELECT/WHERE behavior. It does not introduce WordPress-specific APIs or fixture names.

## Dependency Closure

No new support component is needed. The shard reuses existing `SQLiteSelectSql` parser/executor behavior and the existing PHP `TestRunner`.
