# B-tree Vacuum Pointer-Map Freeblock Current Source Next224

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`.
- Composes accepted `next218` write receipts and adds current-source next-page cursor sequencing for vacuumed pointer-map/freeblock writes.
- Proves pointer-map source pages are visible before payload source pages advance, freeblock receipts remain carried, and fenced tail pages stay out of the source cursor.

## WordPress Smoke

- `examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next224.php`
- Scenario: copied `wp_options` transient cleanup deletes an overflow-backed row, vacuums tail pages, and chains current-source next-page receipts before payload source pages are exposed.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Test.php`
- Result: `1 test files, 1039 assertions, 0 failures`
- PASS-line delta: `139`

## Non-Overlap

This slice adds current-source next-page cursor sequencing after `next218` write receipts. It does not repeat `next218` write receipt construction, `next212` apply ordering, overflow freelist release, page relocation, root collapse, accepted freeblock materialization, or any queued suite/status-only evidence.

## Dependency Closure

No new support component is needed. The slice reuses existing native B-tree, pointer-map, overflow, table-leaf, and record helpers.
