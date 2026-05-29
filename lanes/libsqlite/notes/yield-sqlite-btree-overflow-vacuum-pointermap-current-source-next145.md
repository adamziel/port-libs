# B-tree Overflow Vacuum Pointer-Map Current Source Next145

## Behavior

Adds `SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan` for a current-source auto-vacuum path where copied `wp_options` deletes obsolete overflow chains at the database tail, incremental vacuum truncates part of that tail, and a subsequent oversized option rewrite allocates a new overflow chain.

The slice records:

- current-source overflow chain links and pointer-map parents before deletion;
- released overflow pages and vacuum survivor/truncation boundaries;
- allocation that reuses only surviving free pages and appends fresh pages when the replacement chain is larger;
- final overflow next-page links and pointer-map ownership after allocation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNext145Test.php`
- Result: `1 test files, 283 assertions, 0 failures`
- New focused PASS lines: `79`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-btree-overflow-vacuum-pointermap-current-source-next145.php --self-test`
- Result: `wordpress-btree-overflow-vacuum-pointermap-current-source-next145 self-test passed`

## Non-overlap

This is not the accepted overflow freelist release/freeblock/page-move cluster. The new behavior covers a chained sequence where vacuum truncates tail overflow pages, allocation skips those truncated pages, reuses only surviving freelist pages, and appends fresh overflow pages with new pointer-map ownership.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP b-tree overflow chain readers, freelist release/truncation plans, pointer-map entry handling, and overflow allocation/materialization.
