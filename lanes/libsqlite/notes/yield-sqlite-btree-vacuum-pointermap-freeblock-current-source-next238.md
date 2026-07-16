# B-tree Vacuum Pointer-map Freeblock Current-source Next238

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext238Plan`, a current-source follow-up to next235 that admits checkpointed reusable payload pages into a freelist-link window only after:

- pointer-map barrier pages are visible;
- next235 freeblock receipts are still carried;
- duplicate pointer-map generations are preserved;
- reusable payload pages are linked monotonically;
- fenced tail pages remain excluded from freelist admission.

This is a Application copied-`wp_options` transient delete/vacuum scenario for overflow-backed rows. It does not repeat next235 checkpoint admission, next232 handoff admission, overflow freelist release, page relocation, root collapse, index-interior merge, or bulk overflow freeblock materialization.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext238Test.php`
  - `1 test files, 1254 assertions, 0 failures`
  - `134` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next238.php`
  - `application-btree-vacuum-pointermap-freeblock-current-source-next238 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext238Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext238Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next238.php`

## Dependency Closure

No new support component is needed. The slice reuses the accepted current-source B-tree delete/vacuum planning stack and adds only freelist-link admission metadata on top of next235 checkpoint rows.
