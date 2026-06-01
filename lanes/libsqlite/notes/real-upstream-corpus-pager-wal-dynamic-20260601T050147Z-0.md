# real-upstream-corpus-pager-wal-dynamic-20260601T050147Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode.test`
  - `jrnlmode-5.*` journal size limit, persistent journal clamp, unlimited negative limit, and zero-limit truncation cases.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode2.test`
  - `jrnlmode2-1.*` persistent journal sidecar readability with shared locks.
  - `jrnlmode2-2.*` truncate-mode empty journal sidecar readability.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jrnlmode3.test`
  - `jrnlmode3-3.*` journal mode transitions across `delete`, `persist`, `truncate`, `memory`, and `off`, including failed changes inside active transactions.

## Behavior Delta

- `SQLitePragmaJournalState` now preserves the current `journal_mode` when a change is attempted during an active transaction, matching `jrnlmode3-3.$cnt.3`.
- `SQLitePragmaJournalState` now parses and stores `PRAGMA journal_size_limit`, including large positive values, negative unlimited values, and zero.
- Added `persistentJournalCommitResult()` to model persistent rollback-journal sidecar sizing after commit for `PERSIST` and `TRUNCATE` modes.
- Updated the direct journal-state exact schema assertion for the new `journal_size_limit => -1` default and changed that touched legacy domain-shaped fixture schema name to `app`.

## Focused Evidence

- Pre-fix probe:
  - `php -r 'require "lanes/libsqlite/src/SQLitePragmaJournalState.php"; $c = "PortLibs\\\\LibSqlite\\\\SQLitePragmaJournalState"; $s = new $c(["main" => ["journal_mode" => "persist"]]); $s->begin(); $r = $s->execute("PRAGMA journal_mode = off"); echo $r["rows"][0]["journal_mode"], "\n";'`
  - Result before patch: `off`; expected upstream behavior is to keep `persist`.
- New focused corpus:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalJournalModeDynamic20260601T050147ZTest.php`
  - Result: `1 test files, 42020 assertions, 0 failures`
  - PASS cases: 1002.
- Direct journal-state family:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSynchronousJournalCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJournalStateTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalPersistModeDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalJournalModeDynamic20260601T050147ZTest.php`
  - Result: `5 test files, 62130 assertions, 0 failures`

## Non-Overlap

This slice avoids accepted WAL checkpoint transaction, rollback-journal apply/commit, VFS sync/apply, lock-state/process-lock, savepoint byte truncation, B-tree page move/root collapse/overflow-freelist, JSON table cursor/source/constraint, grouped SELECT, expression ORDER BY, and Unicode GLOB batches. It covers the unowned upstream rollback-journal mode and journal-size-limit behavior from the `jrnlmode` family.

## Dependency Closure

No new support component is needed. The slice extends the existing bounded native PHP `SQLitePragmaJournalState` model and reuses the existing focused TestRunner path.
