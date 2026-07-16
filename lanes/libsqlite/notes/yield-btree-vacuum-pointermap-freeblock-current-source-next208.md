# B-tree Vacuum Pointer-Map Freeblock Current-Source Next208

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext208Plan`.
- Builds on the accepted next206 sealed writer admission by publishing a source-next reader handoff.
- Groups pointer-map source pages `[2, 105]` before payload pages `[3, 106, 107, 108]`.
- Carries the final next206 seal token into chained next208 source-next tokens.
- Keeps truncated tail pages `[109, 110]` fenced from the next reader.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext208Test.php`
- Result: `1 test files, 845 assertions, 0 failures`
- PASS-line delta: `+125`

## Application Smoke

- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next208.php`
- Scenario: copied `wp_options` transient overflow delete plus vacuum source handoff.

## Dependency Closure

No new support component is needed. The slice reuses native B-tree leaf/freeblock handling, overflow payload planning, auto-vacuum pointer-map metadata, and the next206 sealed current-source rows.

## Non-Overlap

This patch adds next208 source-next reader admission after next206 sealing. It does not repeat next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices.

## Root Harness

Not run - isolated micro-slice.
