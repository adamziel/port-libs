# Trigger Recursive View Returning Current Source Close

Consolidated the recursive view-trigger `RETURNING` cursor-close handoff into
the canonical
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose()`
entry point. The direct focused test and Application smoke now use stable
descriptive filenames:

- `SQLiteTriggerRecursiveViewReturningCurrentSourceCloseTest.php`
- `application-trigger-recursive-view-returning-current-source-close.php`

The touched production helper/result/option surface now uses the descriptive
`source_close` suffix instead of the removed worker-numbered cursor-close
suffix. Behavior is unchanged: the current-source recursive child rows remain
visible while the staged next-source rows are fenced until the current
RETURNING cursor close token and ordered close receipts are acknowledged.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceCloseTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-close.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceCloseTest.php`
  -> `1 test files, 89 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-close.php`
  -> `application-trigger-recursive-view-returning-current-source-source_close self-test passed`
- Touched cursor-close numbered suffix scan: no matches for the removed
  cursor-close worker suffix in the canonical class, direct test, example, or
  note.

Dependency closure: no new support component is needed; this reuses the native
recursive view trigger `RETURNING` handoff chain, current-source source
tickets, row tagging, and focused TestRunner evidence.

Non-overlap: this is consolidation-only and avoids next218 epoch admission,
next222 source-ticket behavior, row-value `RETURNING`, DML conflict handling,
schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.
