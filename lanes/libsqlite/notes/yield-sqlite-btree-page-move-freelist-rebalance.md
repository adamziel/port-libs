# B-tree Page Move Freelist Rebalance

## Scope

- Added `SQLiteBTreePageMoveFreelistRebalancePlan`.
- The planner deletes an overflow-backed index cell, applies index leaf rebalance,
  releases obsolete overflow pages into the freelist, then moves the last index
  leaf into the newly available freelist slot in one current-source database
  image.
- This avoids overlapping accepted standalone page relocation, standalone
  overflow freelist release, root collapse, index-interior merge, and bulk
  overflow freeblock slices.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePageMoveFreelistRebalanceTest.php`
- Result: `1 test files, 170 assertions, 0 failures`
- PASS lines: 66

## WordPress Relevance

The fixture models a copied `wp_options` autoload/option-name index where a
large transient index entry is deleted, sibling leaves rebalance, obsolete
overflow pages enter the freelist, and auto-vacuum moves the tail index leaf
into the new freelist slot while preserving parent and pointer-map state.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
B-tree, overflow, freelist, pointer-map, and auto-vacuum page-move primitives.

## Next

Continue B-tree work on remaining delete/rebalance materialization that is not
covered by accepted page relocation, overflow release, index-interior merge,
or this page-move-after-freelist behavior.
