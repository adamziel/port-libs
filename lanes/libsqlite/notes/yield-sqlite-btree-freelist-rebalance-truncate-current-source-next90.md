# B-tree Freelist Rebalance Truncate Current Source Next90

## Behavior

Adds `SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan` for the combined current-source write path where deleting an overflow-backed `wp_options` index record rebalances adjacent index leaves, releases obsolete overflow pages to the freelist, then immediately truncates a contiguous free tail from the database image.

This is intentionally narrower than accepted standalone overflow truncation, overflow freelist release, rebalance pointer-map diagnostics, page relocation, root collapse, and index-interior merge work. The new behavior proves the post-rebalance freelist/truncate materialized database image, surviving free pages, omitted tail pages, pointer-map transition rows, and final byte length.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNext90Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 278 assertions, 0 failures
```

The focused run emits 68 PASS lines for the new current-source B-tree behavior.

## Application Smoke

Command:

```bash
php lanes/libsqlite/examples/application-btree-rebalance-truncate-current-source-next90.php
```

The smoke builds a copied `wp_options`-style option-name index where deleting a large transient key rebalances index leaves, releases overflow pages 406-412, keeps 406-408 as freelist pages, truncates 409-412 from the database image, and reports the final page count and materialized byte length without ext/sqlite.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree index page, record, overflow page, freelist, pointer-map, and database image helpers.
