# B-tree Vacuum Pointer-map Freeblock Current-source Next228

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext228Plan`.
- Builds on accepted `next224` current-source next-page cursor receipts and adds drain/finalization rows for the next writer.
- Verifies drained pages match source pages, resume links match source next links, duplicate pointer-map page 105 is ordered after first visibility, secure-delete freeblock receipts survive, and vacuum-fenced tail pages 109/110 stay out of the drain.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext228Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext228Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next228.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext228Test.php`
  - Result: `1 test files, 965 assertions, 0 failures`
  - PASS-line delta: 130
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next228.php`
  - Result: `application-btree-vacuum-pointermap-freeblock-current-source-next228 self-test passed`

## Non-overlap

This slice adds current-source drain finalization after `next224` cursor sequencing. It does not repeat `next224` next-page links, `next218` write receipt construction, `next212` apply ordering, overflow freelist release, page relocation, root collapse, accepted freeblock materialization, or queued status-only evidence.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP B-tree page images, leaf delete/freeblock receipts, overflow-chain truncation fences, pointer-map current-source rows, and the accepted `next224` source cursor metadata.
