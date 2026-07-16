# SQLite expression covering ORDER BY current-source next103

Slice: `sqlplanner-expression-covering-order-current-source-next103`

Behavior:
- Adds `SQLiteExpressionCoveringOrderCurrentSourceNextPlan` for a lower(option_name) expression index that can satisfy a range predicate and ORDER BY from the current source after schema-cookie/STAT4/index-signature changes.
- Materializes the VDBE-style cursor tape for the selected current index: expression seek/stop opcodes, STAT4 current/next sample segments, output columns read from the index, no deferred table seek, and no temp sorter when the expression index is covering and order-compatible.
- Adds fallback evidence for same-source prepared reuse, BETWEEN inclusive stop opcodes, open lower bounds, missing covering-column table lookup, descending ORDER BY mismatch, and validation errors.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionCoveringOrderCurrentSourceNext103Test.php`
- Result: `1 test files, 76 assertions, 0 failures` with 76 PASS lines.
- `php lanes/libsqlite/examples/application-expression-covering-order-current-source-next103.php --self-test`
- Result: `application-expression-covering-order-current-source-next103 self-test passed`

Dashboard/status delta:
- `lane-status.json` `phpPass`: `39474 -> 39550` (+76 verified focused PASS lines).
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: `587 / 1589 -> 588 / 1589` for the focused SELECT planner expression-index covering ORDER BY current-source evidence row.

Non-overlap:
- Does not repeat accepted parser-level SQL expression ORDER BY execution, expression-index range-cost ranking, STAT4 column-order covering current-source `next99`, or batch99 STAT4 order-covering current-source coverage.
- This slice is specifically expression-index current-source cursor materialization for `lower(option_name)` ORDER BY with covering-index table/sorter elision.

Dependency closure:
- No new support component is needed. The patch reuses existing native index-expression parsing, STAT4 sample data, and lane-local planner diagnostics.

Root harness:
- Not run - isolated micro-slice.
