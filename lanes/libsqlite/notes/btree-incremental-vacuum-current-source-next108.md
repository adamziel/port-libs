# B-tree Incremental Vacuum Current Source Next108

This slice extends the accepted freelist pointer-map vacuum reuse planner with
current-source rows for the overflow pages that incremental vacuum sees after
delete release.

Behavior covered:

- released overflow pages keep their current pointer-map ownership as
  `first-overflow-page` / `overflow-page` before vacuum;
- pages that survive the bounded tail truncation are distinguished from pages
  removed from the database image;
- reused survivor pages report the next `btree-page` pointer-map parent, while
  truncated tail pages report no next pointer-map entry;
- allocation order remains SQLite freelist order, so the reusable leaf page is
  consumed before the surviving trunk page even though source rows remain in
  overflow-chain order.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIncrementalVacuumCurrentSourceNext108Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-incremental-vacuum-current-source-next108.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIncrementalVacuumCurrentSourceNext108Test.php`
  - `1 test files, 265 assertions, 0 failures`
  - 55 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-incremental-vacuum-current-source-next108.php`
  - emits JSON with `allocatedPages` `[307, 306]` and
    `vacuumTruncatedPages` `[308, 309, 310]`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This avoids accepted overflow freelist release, pointer-map vacuum truncation,
table/index page relocation, batch104 freelist pointer-map vacuum reuse,
overflow freepage vacuum reuse, root collapse, and delete/rebalance freeblock
materialization. The new behavior is the current-source row surface that spans
all released overflow pages and explains whether each page is reused as a
next b-tree page, survives as a free page, or is removed by incremental vacuum.

Dependency closure:

No new support component is needed. The patch composes existing native PHP
database image, overflow release, freelist allocation, pointer-map, and
incremental-vacuum truncation primitives.
