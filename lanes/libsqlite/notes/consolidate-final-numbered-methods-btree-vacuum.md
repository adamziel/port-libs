2026-05-29 - consolidate final B-tree vacuum numbered methods

Scope:
- Consolidated the final `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` production freelist handoff coverage into `tableLeafCurrentSourceFreelistHandoffFromDeleteResult()`.
- Migrated the direct focused test and Application example for the final freelist handoff range to stable descriptive filenames.

Verification:
- `php -l` passed for the changed production file, focused test, and example.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffTest.php` passed: 1 test files, 672 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.
- Targeted source scan found no remaining final-range numbered B-tree vacuum test/example filenames for this handoff coverage.

Dependency closure:
- No new support component is needed; this is a production API consolidation over existing B-tree vacuum pointer-map/freeblock behavior.

2026-05-29 - sixth pass

Scope:
- Consolidated `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` production entrypoints `tableLeafFromDeleteResultNext331()` through `tableLeafFromDeleteResultNext414()` into the stable canonical `tableLeafFreelistSpliceFromDeleteResult()` dispatcher.
- Migrated the direct focused tests and Application examples for ranges 331-334, 335-342, 343-350, 351-358, 359-366, 367-374, 375-382, 383-390, 391-398, and 399-414 to the stable method.

Verification:
- `php -l` passed for 21 changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext331334Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext335342Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext343350Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext351358Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext359366Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext367374Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext375382Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext383390Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext391398Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext399414Test.php` passed: 10 test files, 1596 assertions, 0 failures.
- Changed Application examples for ranges 331-414 ran successfully and emitted valid JSON rowsets: 4, 8, 8, 8, 8, 8, 8, 8, 8, and 16 rows.
- `git diff --check -- lanes/libsqlite` passed.
- Targeted source/test/example scan found 0 remaining `tableLeafFromDeleteResultNext331` through `tableLeafFromDeleteResultNext414` references; remaining production numbered method-line audit is 8701.

Dependency closure:
- No new support component is needed; this is a production API consolidation over existing B-tree vacuum pointer-map/freeblock behavior.
