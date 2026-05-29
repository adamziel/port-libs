# B-tree Vacuum Pointer-map Freeblock Current Source Next189

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a resumable current-source checkpoint verifier layered on next186 cursor visibility. It records newly visible pages, monotonic high-water pages, prior/current resume tokens, pointer-map-before-payload fencing, deleted-cell hiding, fenced-tail hiding, and checkpoint tokens for a copied `wp_options` overflow delete followed by vacuum and replacement overflow publication.

## Evidence

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Test.php`: `1 test files, 594 assertions, 0 failures` with 94 PASS lines.
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next189.php`: self-test passed.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next189.php`: no syntax errors.
- `git diff --check -- lanes/libsqlite`: passed.

## Non-Overlap

This slice does not repeat accepted next186 cursor visibility, next185 post-apply receipts, next183 commit receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization. It adds restart/checkpoint admission over the visible current-source batches.

## Dependency Closure

No new support component is needed. The slice reuses native b-tree page assembly, auto-vacuum pointer-map metadata, current-source cursor rows, page hashes, and resume-token plumbing.
