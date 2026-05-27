# B-tree Freepage Allocation Current/Next21

## Behavior

This slice adds lane-local allocation provenance to `SQLiteFreelistAllocationPlan` while preserving the existing `toArray()` summary shape. `SQLiteDatabase::planPageAllocation()` now records each allocated page as coming from a current freelist trunk leaf, the current trunk page itself, the next trunk after handoff, or append fallback. `planBtreePageAllocation()` carries the same provenance through auto-vacuum pointer-map decoration.

The focused scenario covers the SQLite freelist edge where a copied `wp_options` b-tree write drains leaf entries from the current trunk, consumes that empty current trunk, follows the current trunk's next pointer, drains and consumes the next trunk, and appends only after the freelist is depleted.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreepageAllocationCurrentNext21Test.php`
- Result: `1 test files, 52 assertions, 0 failures`
- PASS-line delta for this focused file: `+52`

## Non-Overlap

This does not repeat accepted B-tree overflow freelist release, bulk overflow freeblock materialization, table/index page relocation, root collapse, index-interior merge, or parent-prune diagnostics. It stays on freepage allocation order/provenance and auto-vacuum pointer-map ownership for newly allocated B-tree pages.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP SQLite database image, freelist trunk, header, and pointer-map primitives.
