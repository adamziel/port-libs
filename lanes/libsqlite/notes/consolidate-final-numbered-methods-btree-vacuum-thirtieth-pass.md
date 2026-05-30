# B-tree vacuum numbered method consolidation thirtieth pass

Migrated the B-tree vacuum pointer-map/freeblock current-source direct `895-910`
test and WordPress smoke away from removed generated method dispatch. Coverage now
uses the stable `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafCurrentSourceHandoffFromDeleteResult()`
entry point, and the direct test/example filenames are unsuffixed.

Validation:

- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceHandoffTest.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceHandoffTest.php` -> `1 test files, 24 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-handoff.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is caller/test/example
consolidation over the existing canonical B-tree vacuum implementation.
