# B-tree Vacuum Pointer-map Freeblock Current-source Next211

Date: 2026-05-28T17:05:00Z

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext211Plan`, a replay-apply receipt layer on top of the accepted next209 latched writer-source rows. The plan converts each latched pointer-map or payload/freeblock source row into an ordered apply row, checks pointer-map barriers before payload application for every cursor, carries leaf freeblock receipts forward, chains apply tokens, and keeps vacuum-fenced tail pages out of the replay set.

This targets the Application copied `wp_options` transient delete/vacuum path where a next writer must replay the current-source pages in an order that preserves auto-vacuum pointer-map correctness before reusing freeblock payload pages.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext211Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1088 assertions, 0 failures
```

The focused run produced 142 PASS lines for the new next211 test file.

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next211.php
```

Expected self-test line:

```text
application-btree-vacuum-pointermap-freeblock-current-source-next211 self-test passed
```

## Non-overlap

This slice is after next209 source latching. It does not repeat next209 latching, next206 sealing, next203 cursor batching, next196 handoff, accepted overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The patch reuses existing B-tree page, pointer-map, table leaf, record, and current-source writer rows.

## Next Task

Continue B-tree work on a distinct materialized write path that consumes replay receipts into full page images or freelist trunk updates, rather than adding another seal/latch layer.
