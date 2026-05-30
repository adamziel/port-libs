# Root Gate Window Groups Range Next18 Dynamic

Scope: tightened direct `SQLiteSelectQuery` window validation so any explicit
`RANGE` or `GROUPS` frame without a window `ORDER BY` is rejected before
function-specific dispatch. This keeps the SQL parser path and array-plan path
aligned for aggregate, JSON aggregate, value, and ranking window functions.

Pre-fix reproduction on this worktree:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 63 assertions, 0 failures
```

The supervisor-reported root failure did not reproduce on base
`39fc7983933246e7a2f21b2359c0b9f7e583340a`, so this patch adds regression
coverage for the remaining direct-plan/value-window bypass shape instead of
weakening any assertions.

Focused verification:

```bash
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# no syntax errors

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 65 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveMaterializedWindowCurrentNext38Test.php
# 1 test files, 50 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowExcludeFilterRangeCurrentNext51Test.php lanes/libsqlite/tests/SQLiteVdbeWindowFilterRangeExcludeCurrentNext53Test.php lanes/libsqlite/tests/SQLiteVdbeWindowPeerRangeCurrentNext30Test.php lanes/libsqlite/tests/SQLiteVdbeWindowRangePeerCurrentNext34Test.php lanes/libsqlite/tests/SQLiteVdbeWindowValueGroupsRangeCurrentNext50Test.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php
# 8 test files, 465 assertions, 0 failures

git diff --check -- lanes/libsqlite
# clean
```

Dependency closure: no new support component is needed; the patch reuses the
existing SELECT query window frame validator.

Non-overlap: this is limited to the root-gate `RANGE`/`GROUPS` no-ORDER
validation path. It does not repeat suite-evidence current-next bounded row
work, VFS/WAL/B-tree, JSON table, STAT4, or numbered production consolidation
surfaces.
