# B-tree Mutation Apply Current/Next56

## Behavior

This slice wires current/next fragment absorption into the table and index leaf mutation paths. When a delete creates a freeblock separated from the previous freeblock by a 1-3 byte fragment, the mutation now coalesces the fragment into the rewritten freeblock chain and decrements the page fragmented-byte counter. If the page header does not account for the fragment bytes, the mutation rejects the page instead of materializing corrupt free-space accounting.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeMutationApplyCurrentNext56Test.php`
- Result: `1 test files, 40 assertions, 0 failures`
- New focused PASS lines: `40`
- Expected lane-status movement: `phpPass` `20008 -> 20048`; mapped denominator unchanged at `462 / 1589`.

## Application Smoke

- `php lanes/libsqlite/examples/application-btree-mutation-current-next56.php`
- Scenario: copied `wp_options` transient delete applies a table-leaf mutation, absorbs a two-byte current/next fragment into the coalesced freeblock, preserves freeblock integrity, and secure-deletes the merged payload bytes without `ext/sqlite`.

## Non-Overlap

Avoids accepted B-tree page relocation, root collapse, index-interior merge, bulk overflow freeblocks, overflow freelist release, freelist tail truncation, freeblock coalesce diagnostics, and pointer-map apply clusters. This patch changes the live table/index leaf delete mutation path rather than adding another standalone diagnostic wrapper.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree page, cell, record, and freeblock parsing primitives.
