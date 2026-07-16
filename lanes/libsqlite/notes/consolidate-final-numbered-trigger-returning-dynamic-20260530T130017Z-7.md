# Consolidate Final Numbered Trigger RETURNING Dynamic

Slice: `consolidate-final-numbered-trigger-returning-dynamic-20260530T130017Z-7`

Changed behavior:

- Added `SQLiteFinalNumberedTriggerReturningDynamicConsolidationTest.php`.
- The guard dynamically scans trigger production classes under `lanes/libsqlite/src/*Trigger*.php` and verifies numbered `NextNN`/`CurrentNextNN`/`CurrentSourceNextNN` names are not exposed as production class names or direct public methods.
- The guard also pins the stable trigger RETURNING / recursive-view UPSERT canonical entry points used by the final dynamic consolidation path.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteFinalNumberedTriggerReturningDynamicConsolidationTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteFinalNumberedTriggerReturningDynamicConsolidationTest.php`
  - `1 test files, 85 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteFinalNumberedTriggerReturningDynamicConsolidationTest.php`
  - `42 test files, 3572 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses the existing native trigger RETURNING and recursive-view UPSERT consolidation classes and adds a focused regression guard for public API naming.

Non-overlap:

- This does not add trigger behavior or status counters. It specifically guards final numbered trigger RETURNING dynamic consolidation and avoids row-value, STAT4, WAL, pager, JSON, B-tree, PRAGMA, suite-evidence, and WordPress-specific API surfaces.
