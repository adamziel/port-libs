# B-tree freelist vacuum pointer-map current-source next139

## Behavior

This slice adds `SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan`, a bounded current-source composition for auto-vacuum databases:

- release obsolete overflow pages from copied `wp_options` table/index delete results;
- run incremental-vacuum tail truncation over the resulting freelist;
- allocate a replacement overflow chain only from surviving freelist pages;
- expose transition rows proving truncated tail pages are not reused and allocated survivors receive fresh overflow pointer-map ownership.

This is intentionally distinct from accepted overflow freelist release/reuse, bulk overflow freeblock materialization, page relocation, and vacuum-only pointer-map reporting. The new behavior covers the combined current-source path where vacuum changes the page boundary before replacement overflow allocation.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 359 assertions, 0 failures
```

The focused file emits 79 PASS lines.

Application smoke:

```text
php lanes/libsqlite/examples/application-btree-freelist-vacuum-pointermap-current-source-next139.php --self-test
application-btree-freelist-vacuum-pointermap-current-source-next139 self-test passed
```

## Dependency Closure

No new support component is required. The slice reuses existing native PHP SQLite page-image, freelist, pointer-map, overflow, and vacuum-truncation primitives already present under `lanes/libsqlite/src`.
