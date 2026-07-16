# SQLite Planner Covering Partial Skip-Scan Current Source Next125

## Behavior

Added `SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSourceNext125()` to
fence covering partial skip-scan plans against schema-cookie, STAT4 generation,
index root, row payload, STAT4 sample, bounds/collation, and covering-column
signature changes. A stale prepared plan is replanned against the current source;
an unchanged source reuses the prepared covering skip-scan plan.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringPartialSkipScanCurrentSourceNext125Test.php`
  passed with `1 test files, 56 assertions, 0 failures` and 56 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-planner-covering-partial-skipscan-current-source-next125.php`
  passed and emitted a current-source reprepare summary with rowids `[2,3,7,11]`.
- Syntax check and `git diff --check -- lanes/libsqlite` were run for this lane patch.

## Non-Overlap

This does not repeat accepted STAT4 expression range-cost, expression `ORDER BY`,
JSON hidden/visible constraints, VFS writer/locking/sync, WAL checkpoint,
rollback-journal, B-tree page-move/root-collapse/overflow freelist, or prior
single-source skip-scan covering behavior. The slice adds the current-source
selection fence for covering partial skip-scan plans.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
skip-scan, partial predicate, and STAT4 covering planner helpers.
