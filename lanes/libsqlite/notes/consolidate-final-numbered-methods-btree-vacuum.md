2026-05-29 - consolidate final B-tree vacuum numbered methods

Scope:
- Consolidated the final `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` production entrypoints `tableLeafFromDeleteResultNext1151()` through `tableLeafFromDeleteResultNext1182()` into `tableLeafFromDeleteResultForCurrentSourceFreelistHandoff()`.
- Migrated the direct focused tests and WordPress examples for ranges 1151-1166 and 1167-1182 to the stable descriptive method.

Verification:
- `php -l` passed for the changed production file, two focused tests, and two examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11511166Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11671182Test.php` passed: 2 test files, 672 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1151-1166.php` and `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1167-1182.php` both produced 16 valid rows with empty error lists, matching freelist pages, and excluded tail pages.
- `git diff --check -- lanes/libsqlite` passed.
- Targeted source scan found no remaining `tableLeafFromDeleteResultNext1151` through `tableLeafFromDeleteResultNext1182` references in the changed production/test/example files.

Dependency closure:
- No new support component is needed; this is a production API consolidation over existing B-tree vacuum pointer-map/freeblock behavior.
