# B-tree Overflow Pointer-Map Freelist Current Source Next125

Date: 2026-05-28T07:03:34Z

## Behavior

Adds `SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan` for the
WordPress large-option rewrite path where obsolete overflow pages are released
to an auto-vacuum freelist and then immediately reused as the next overflow
chain. The covered transition is:

- current pointer-map entries: `first-overflow-page` / `overflow-page`
- release transition: both pages become `free-page` with parent `0`
- next allocation: the same pages become `first-overflow-page` and
  `overflow-page` with restored parent chain `3 -> 6`

This intentionally avoids accepted next121 B-tree page reuse, next122
freeblock/vacuum materialization, next120 overflow rebalance/freeblock work,
and the accepted overflow freelist release surface. The new slice covers
overflow-page reallocation back into an overflow chain, not B-tree page reuse
or another standalone freelist release wrapper.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNext125Test.php`
  - `1 test files, 166 assertions, 0 failures`
  - 76 PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-overflow-pointermap-freelist-current-source-next125.php --self-test`
  - `wordpress-btree-overflow-pointermap-freelist-current-source-next125 self-test passed`
- PHP lint was run for the changed PHP source, test, and example.
- `git diff --check -- lanes/libsqlite` was run after the note/status update.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
SQLite page, freelist, overflow-page, pointer-map, and integrity-check
primitives already under `lanes/libsqlite/src`.

## Next

Continue with non-overlapping B-tree delete/rebalance or pointer-map apply
behavior, preferably page-image application around empty-leaf or freelist
materialization not already covered by next120-next122/next125.
