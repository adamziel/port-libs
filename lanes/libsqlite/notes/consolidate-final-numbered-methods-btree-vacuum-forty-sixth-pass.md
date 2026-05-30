# B-tree Vacuum Numbered Method Consolidation Forty-Sixth Pass

- Migrated the 991-1006 B-tree vacuum freelist handoff direct callers from dynamic numbered method-name construction to the stable `tableLeafCurrentSourceFreelistHandoffFromDeleteResult()` entry point on `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`.
- Folded the migrated range into the existing descriptive freelist-handoff focused test and Application smoke, preserving the existing 1135-1182 coverage while deleting the old numbered direct test/example filenames.
- Dependency closure: no new support component is needed; this reuses the canonical B-tree vacuum current-source freelist handoff implementation already in production.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffTest.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffTest.php` -> `1 test files, 1344 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff.php --self-test` -> self-test passed
- `git diff --check -- lanes/libsqlite`
