# real-upstream-corpus-pager-wal-dynamic-20260531T154717Z-0

- Lane: `libsqlite`
- Base accepted HEAD: `e32a8526a498fd08ddb3ac17a87c7ee9473f44b5`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/mjournal.test`
- Patch scope: generic pager/master-journal behavior only, under `lanes/libsqlite/**`.

## Upstream Sections

- `mjournal-1.1..1.2`: rollback journal pointer name `test.db2journal` lacks a `-`; the reader ignores it safely and `SELECT * FROM t1` remains safe.
- `mjournal-1.3..1.4`: rollback journal pointer name `test0db2journal` lacks a `-`; the reader ignores it safely and remains crash-free.
- `mjournal-1.5..1.6`: master-journal file contains member `test1` without a `-`; the member is ignored safely.
- `mjournal-2.1`: two modified real database files require a master journal.
- `mjournal-2.2`: one modified real database plus one temporary database does not require a master journal.
- `mjournal-2.3`: one modified real database plus one memory database does not require a master journal.

## Implementation

- Added `SQLiteMasterJournalDynamicPlan`, a source-neutral dynamic helper that models upstream master-journal pointer safety and creation-gate behavior.
- Added `SQLiteRealUpstreamCorpusPagerWalDynamicMasterJournal20260531Test`, which cites the hydrated upstream `mjournal.test` file and generates 1000 distinct TestRunner cases across the six upstream scenarios.
- Expected selected `phpPass` movement: `3137763 -> 3138766` (`+1003`) from the focused TestRunner cases.
- Focused assertion evidence: `1 test files, 21862 assertions, 0 failures`.
- Mapped coverage remains `1589 / 1589`; this is new focused behavior over an already mapped upstream denominator.

## Non-Overlap

This slice covers `mjournal.test` legacy master-journal name safety and real/temp/memory master-journal creation gates. It avoids accepted pager1 master-journal boundary, journal2 safe-delete, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock, WAL byte truncation, walshared, e_walhook, app-WAL, where2/date/localtime/nullable-IN/temp-PRAGMA, and GROUP BY collation slices.

## Dependency Closure

No new support component is needed. The patch reuses the hydrated upstream SQLite `mjournal.test` corpus file and a source-neutral PHP helper under `lanes/libsqlite/src`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteMasterJournalDynamicPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicMasterJournal20260531Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicMasterJournal20260531Test.php` -> `1 test files, 21862 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`
- `git diff --check -- lanes/libsqlite` -> no whitespace errors
- Root harness: not run - isolated micro-slice
