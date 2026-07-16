# SQLite Planner Partial Covering Skip-Scan Current Source Next127

## Behavior

Added `SQLiteSkipScanStat4PartialOrderPlan::partialCoveringSkipScanCurrentSourceNext127()`.
It replans stale prepared partial covering skip-scan statements against the
current source while reducing `ORDER BY` expressions through deterministic
equality predicates. For the Application `wp_options` shape, `WHERE kind =
'plugin' ORDER BY kind, option_name` now prunes the constant `kind` term and
keeps the covering skip-scan over `option_name`; uncovered expressions such as
`lower(option_name)` force a deferred table lookup unless the expression is
present in the covering index image.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialCoveringSkipScanCurrentSourceNext127Test.php`
  passed with `1 test files, 57 assertions, 0 failures` and 57 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-planner-partial-covering-skipscan-current-source-next127.php --self-test`
  passed.
- Syntax check and `git diff --check -- lanes/libsqlite` were run for this lane patch.

## Non-Overlap

This does not repeat accepted next125 current-source covering fences, STAT4
range-cost ranking, SQL expression `ORDER BY`, JSON hidden/visible constraints,
VFS writer/locking/sync, WAL checkpoint/savepoint byte work, or B-tree
page-move/root-collapse/overflow freelist clusters. The new slice is the
ORDER-expression reduction and covering-preservation decision for partial
covering skip-scan plans.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
skip-scan, partial predicate, STAT4, and covering current-source planner
helpers.
