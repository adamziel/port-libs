# consolidate-final-numbered-methods-btree-vacuum-thirty-third-pass

## Scope

Consolidated remaining numbered factory wrappers in the B-tree vacuum-adjacent pointer-map/freeblock families touched by the B-tree vacuum production file:

- `SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next127*` -> `base*`
- `SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144*` -> `extended*`
- `SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::next128FromDeleteResults()` -> `baseFromDeleteResults()`
- `SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::next147TableAndIndexFromCurrentSourceDeleteResults()` -> `extendedTableAndIndexFromCurrentSourceDeleteResults()`

Direct B-tree vacuum callers, focused tests, and Application examples were migrated to the stable descriptive methods and filenames. No production compatibility shim with the numbered method names remains for these wrappers.

## Verification

- `php -l` for changed PHP source, tests, and examples: all passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceBaseTest.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceExtendedTest.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceBaseTest.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceExtendedTest.php`: `4 test files, 1208 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-btree-pointermap-vacuum-freeblock-current-source-extended.php --self-test`: passed.
- `php lanes/libsqlite/examples/application-btree-overflow-freeblock-pointermap-current-source-base.php --self-test`: passed.
- `php lanes/libsqlite/examples/application-btree-overflow-freeblock-pointermap-current-source-extended.php --self-test`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This is a production method/file/caller consolidation only.
