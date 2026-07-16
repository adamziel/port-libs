# B-tree Overflow Freeblock Vacuum Current Source Next122

## Behavior

- Adds `SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext122Plan`.
- The plan coalesces fragmented freeblocks on a current table leaf page before releasing obsolete overflow chains into the freelist.
- It then applies incremental-vacuum truncation and records which freed pointer-map pages survive as freelist pages versus which tail pages are omitted from the materialized database image.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext122Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-btree-overflow-freeblock-vacuum-current-source-next122.php`
- Syntax checks: changed PHP files are linted.
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice avoids accepted overflow freelist release, bulk overflow freeblock materialization, delete/vacuum pointer-map next119 rows, page relocation, root collapse, and index-interior merge work. The new behavior is the combined current-source application order: freeblock coalescing on the leaf page before overflow release and incremental-vacuum truncation.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree page-header/freeblock parsing, overflow release, pointer-map, freelist, and vacuum truncation components already under `lanes/libsqlite/src`.
