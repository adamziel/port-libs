# B-tree Pointer-Map Overflow Vacuum Merge Current Source Next123

## Behavior

Adds `SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan`, a
current-source B-tree merge view for delete results whose obsolete overflow
chains are first merged into the freelist and then partially truncated by
auto-vacuum.

The next123 plan is intentionally narrower than the accepted next118/119
overflow pointer-map rebalance and delete/vacuum rows. It preserves the
original source pointer-map ownership columns before release, while also
recording the post-merge freelist role, surviving free-page pointer-map type,
truncated tail pointer-map type, materialized page images, and final
auto-vacuum page count.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNext123Test.php`
- Result: `1 test files, 151 assertions, 0 failures`
- PASS-line movement: `+63` focused PASS lines
- Application smoke: `php lanes/libsqlite/examples/application-btree-pointermap-overflow-vacuum-merge-current-source-next123.php`

## Non-Overlap

Avoids accepted `SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan`,
`SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan`,
`SQLiteOverflowFreelistReleasePlan` release-only coverage, and bulk overflow
freeblock/freelist release slices. This patch does not add another freeblock or
overflow-release primitive; it adds the missing merge-facing row projection
that distinguishes original overflow ownership from post-release freelist
state for auto-vacuum materialization.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native
PHP B-tree, pointer-map, freelist, overflow, and vacuum primitives.
