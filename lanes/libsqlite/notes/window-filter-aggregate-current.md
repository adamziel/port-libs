# window-filter-aggregate-current

## Scope

- Tightened shared `SQLiteSelectQuery` window-frame validation so direct JSON aggregate window execution rejects nonnumeric `RANGE` keys and malformed frame metadata the same way built-in aggregate window execution already does.
- Added focused upstream-corpus coverage for filtered `GROUPS` and `RANGE` aggregate windows with `EXCLUDE` interactions on copied `wp_options` rows.

## Evidence

- Before change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php` passed at `1 test files, 46 assertions, 0 failures`; this confirmed the named root-gate frame-without-ORDER blocker was already green in this worktree.
- After change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php` passed at `1 test files, 52 assertions, 0 failures`.
- Related family: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php` passed at `6 test files, 320 assertions, 0 failures`.
- JSON aggregate window regression check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowFilterOrderCurrentSourceNext104Test.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctFilterWindowCurrentSourceNext112Test.php lanes/libsqlite/tests/SQLiteJsonAggregateDefaultWindowCurrentSourceNext100Test.php` passed at `3 test files, 174 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing `SQLiteSelectQuery`, `SQLiteSelectSql`, and window aggregate helpers.

## Non-Overlap

This does not repeat the accepted JSON table cursor/source wiring, expression `ORDER BY`, grouped SELECT text, VFS writer/lock/sync paths, WAL savepoint byte truncation, or B-tree page-move/overflow clusters. The slice is limited to aggregate window `FILTER` and shared `RANGE` frame validation.
