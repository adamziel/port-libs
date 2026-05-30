# B-tree Freelist Pointer-Map Vacuum Reuse Current Source Next104

## Scope

This isolated B-tree slice covers the write step after overflow release and incremental vacuum:

- obsolete overflow pages 306-310 are released from copied `wp_options` delete results;
- incremental vacuum truncates tail pages 308-310;
- the surviving free pages 307 and 306 are allocated, without append, as new B-tree table/index pages;
- auto-vacuum pointer-map entries move from `free-page` parent `0` to `btree-page` parent `42`;
- truncated pages remain unavailable and are not reused.

## Non-Overlap

This avoids accepted overflow freelist release, empty-leaf release, pointer-map vacuum truncation, table/index page relocation, and batch100 delete/rebalance freelist chaining. The new behavior is the subsequent post-vacuum reuse/allocation application over the current source image.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Test.php`
- `php -l lanes/libsqlite/examples/application-btree-freelist-vacuum-reuse-current-source-next104.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Test.php`
  - `1 test files, 262 assertions, 0 failures`
  - 67 focused PASS lines
- `php lanes/libsqlite/examples/application-btree-freelist-vacuum-reuse-current-source-next104.php`
  - emits JSON with `allocatedPages` `[307, 306]` and `vacuumTruncatedPages` `[308, 309, 310]`

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP SQLite file-format, freelist, pointer-map, overflow-vacuum, table leaf, and index leaf primitives.

## Expected Status Delta

- `phpPass`: `40110 -> 40177` (+67 focused PASS lines)
- mapped coverage: `597 / 1589 -> 598 / 1589`
- root harness: not run from this isolated micro-slice
