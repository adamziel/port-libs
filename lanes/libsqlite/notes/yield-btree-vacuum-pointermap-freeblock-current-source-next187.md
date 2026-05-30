# B-tree Vacuum Pointer-Map Freeblock Current Source Next187

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan` to validate the final next-source publish barrier after next184 cursor materialization.
- The focused behavior asserts that scrubbed leaf freeblock receipts are present before publish, the replacement overflow terminal page carries a final zero next-pointer receipt, and truncated vacuum-tail pages remain fenced by pointer-map receipts instead of leaking into the next-source cursor.
- Application smoke: copied `wp_options` transient cleanup with a secure-delete table leaf, replacement overflow chain, auto-vacuum pointer-map pages, and one fenced truncated tail page.
- Non-overlap: this does not repeat next184 cursor materialization, next183 commit receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks.
- Dependency closure: no new support component needed; the slice reuses native table leaf parsing, overflow next-pointer decoding, secure-delete freeblock receipts, and pointer-map metadata already in the lane.
- Focused verification:
  - `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan.php`
  - `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Test.php`
  - `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next187.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Test.php`
  - `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next187.php`
  - `git diff --check -- lanes/libsqlite`
