# B-tree vacuum writer cursor / commit receipt consolidation

Consolidated the b-tree vacuum pointer-map/freeblock writer-cursor through
commit-receipt surface away from numbered current-source names. The production
labels now use stable descriptive names for writer cursor, sealed writer
admission, writer source latch, page apply dependencies, current-source commit,
and current-source commit receipt.

Direct tests and WordPress examples were renamed to the same descriptive
surface names while preserving the existing assertions and smoke coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l` for the 5 renamed focused tests and 5 renamed WordPress examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockWriterCursorTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockSealedWriterAdmissionTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockWriterSourceLatchTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceCommitTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceCommitReceiptTest.php`
  - `5 test files, 4451 assertions, 0 failures`
- `php` for each renamed WordPress example; all emitted self-test passed output.

Dependency closure: no new support component needed; this is a production
name/reference consolidation over existing b-tree vacuum pointer-map/freeblock
behavior.
