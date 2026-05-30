# B-tree Vacuum Pointer-Map Freeblock Current Source Next213

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext213Plan`, a post-apply receipt layer for the current-source B-tree vacuum pointer-map/freeblock path. It consumes the accepted `next212` current-source apply rows and publishes receipt rows that prove:

- receipt pages exactly match the latched apply pages `[2, 3, 105, 106, 107, 108]`;
- pointer-map receipts for pages `2` and `105` precede payload receipts for leaf/overflow pages;
- the deleted leaf freeblock receipt is preserved through publication;
- fenced tail pages `109` and `110` remain excluded from the writer-visible receipt;
- receipt token chaining protects against stale current-source apply tokens.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext213Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 943 assertions, 0 failures
```

The run emitted 143 focused PASS lines.

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next213.php
```

Result:

```text
application-btree-vacuum-pointermap-freeblock-current-source-next213 self-test passed
```

## Non-Overlap

This slice adds post-apply current-source receipt publication after `next212` apply ordering. It does not repeat `next212` page apply ordering, `next209` writer-source latching, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The slice reuses native B-tree vacuum current-source apply rows, pointer-map/payload page classes, leaf freeblock receipts, and fenced-tail metadata already present in the lane.
