# B-tree Vacuum Pointer-map Freeblock Current Source Next229

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a focused follow-up to next224 current-source cursor sequencing. The new plan builds resumable source-window receipts over the vacuum pointer-map/freeblock rows and validates that:

- resume pages match the current-source cursor pages exactly;
- pointer-map resume pages are visible before payload resume pages;
- leaf freeblock receipts remain carried through every resume row;
- truncated tail pages stay fenced from the resumable source window;
- resume tokens chain monotonically and terminate at EOF.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext229Test.php`
  - `1 test files, 1184 assertions, 0 failures`
  - `144` PASS lines
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext229Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next229.php`

## Status Delta

- `phpPass`: `110487 -> 110631` (`+144` focused PASS lines).
- Mapped upstream coverage: unchanged at `628 / 1589`; this is PHP behavior coverage, not a new manifest-backed upstream row.

## Non-overlap

This slice adds resume-window admission after next224 next-page cursor sequencing. It does not repeat next224 cursor construction, next218 write receipts, next212 apply ordering, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or batch199 next220-next224 accepted behavior.

## Dependency Closure

No new support component is needed. The slice reuses native B-tree pages, records, pointer-map entries, table leaf deletion, and existing current-source vacuum/freeblock planning helpers.
