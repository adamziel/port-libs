# B-tree Vacuum Pointer-map Freeblock Current Source Next233

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan`, a focused
follow-up to next229 resume-window admission. The new plan records
checkpoint-admission receipts for the resumable current-source page stream and
validates that:

- checkpoint pages match the next229 resume pages exactly;
- payload checkpoints always have prior pointer-map checkpoint visibility;
- duplicate pointer-map page 105 publishes distinct generation receipts;
- leaf freeblock receipts remain carried into every checkpoint row;
- truncated tail pages 109/110 stay fenced from the checkpoint stream;
- checkpoint tokens chain monotonically across the source window.

## Application Smoke

- `examples/application-btree-vacuum-pointermap-freeblock-current-source-next233.php`
- Scenario: copied `wp_options` transient cleanup deletes an overflow-backed
  option, vacuums tail pages, then admits resumable current-source checkpoints
  only after pointer-map/freeblock receipts are visible.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Test.php`
- Result: `1 test files, 1254 assertions, 0 failures`
- PASS-line delta: `134`

## Status Delta

- `phpPass`: `113830 -> 113964` (`+134` focused PASS lines).
- Mapped upstream coverage remains `634 / 1589`; this is native PHP behavior
  coverage over already mapped B-tree vacuum/pointer-map/freeblock inventory,
  not a fresh manifest-backed upstream row.

## Non-overlap

This slice adds checkpoint-admission receipts after next229 resume windows. It
does not repeat next229 resume construction, next224 cursor sequencing, next218
write receipts, next212 apply ordering, overflow freelist release, page
relocation, root collapse, bulk overflow freeblock materialization, or any
suite/status-only evidence.

## Dependency Closure

No new support component is needed. The slice reuses existing native B-tree
pages, pointer-map entries, table leaf deletion, overflow vacuum, and current
source resume-window helpers.
