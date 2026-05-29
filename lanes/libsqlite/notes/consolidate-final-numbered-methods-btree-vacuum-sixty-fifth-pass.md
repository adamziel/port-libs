# B-tree Vacuum Numbered Method Cleanup Sixty-Fifth Pass

Consolidated the B-tree vacuum pointer-map/freeblock current-source publication
and receipt-publication surfaces in
`SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`.

- Replaced former numbered production status/action/token/state vocabulary with
  stable descriptive publication and receipt-publication names.
- Renamed the two direct tests and WordPress examples to descriptive
  unsuffixed filenames.
- No new support component is needed; this cleanup reuses the existing
  canonical production class and cursor-admission rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePublicationTest.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReceiptPublicationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-publication.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-receipt-publication.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePublicationTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReceiptPublicationTest.php` -> `2 test files, 2744 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-publication.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-receipt-publication.php`
- `git diff --check -- lanes/libsqlite`
