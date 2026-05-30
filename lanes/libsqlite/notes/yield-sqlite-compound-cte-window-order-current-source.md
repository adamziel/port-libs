# compound-select-cte-window-order-current-source

## Behavior

Adds a bounded current-source/candidate-source diagnostic for compound SELECT text whose arms read materialized CTE rowsets, compute ordered window functions inside each arm, and then apply the final compound ORDER BY/LIMIT over the renamed compound output columns.

This avoids the accepted compound row-composition, compound HAVING/window, recursive affinity/window, compound frame LIMIT, grouped SELECT text, subquery, and expression ORDER BY clusters by focusing on CTE materialization feeding ordered window expressions inside compound arms.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCteWindowOrderCurrentSourceTest.php`
- Example smoke: `php lanes/libsqlite/examples/application-compound-cte-window-order-current-source.php`
- PHP lint: changed PHP files under this slice.
- Whitespace: `git diff --check -- lanes/libsqlite`

The focused test contributes 58 new PASS lines. `lane-status.json` increments `phpPass` from 56029 to 56087 for this isolated lane patch.

## Dependency Closure

No new support component is required. The slice reuses native PHP `SQLiteSelectSql`, materialized WITH table handling, compound SELECT execution, window evaluation, and final result ordering.

## Next

Continue with non-overlapping SQL executor/planner current-source gaps such as compound SELECT collation/affinity through CTE boundaries or broader parser/executor integration not covered by accepted grouped SELECT, subquery, expression ORDER BY, or this CTE/window/order slice.
