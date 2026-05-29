# B-tree Vacuum Pointer-Map Freeblock Current Source Next180

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a downstream current-source apply-order plan for the accepted next177 replay batches. The plan builds deterministic write sequences where pointer-map dependency pages are emitted before page-image writes, and it rejects any fenced/truncated tail page that leaks into the apply sequence.

The WordPress smoke models a `wp_options` transient delete with overflow pages 106-110, a vacuum truncation fence on pages 109-110, and readable apply pages `[1, 3, 105, 106, 107, 108]`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 540 assertions, 0 failures
```

PASS-line delta: `+90`.

Syntax checks:

```text
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Test.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next180.php
```

All reported no syntax errors.

## Dependency Closure

No new support component is needed. The slice reuses next177 readable batches, pointer-map dependency pages, page-image hashes, and fenced tail pages.

## Non-Overlap

This slice does not repeat next177 batch construction, next174 cursor fencing, overflow freelist release, root collapse, page relocation, bulk overflow freeblock materialization, or accepted B-tree page-move/interior-merge/root-collapse work. It adds the narrower current-source apply ordering over already materialized replay batches.
