# Compound SELECT Window Recursive LIMIT Current Source Next181

This patch adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a focused current-source wrapper for recursive compound SELECTs where `UNION ALL` feeds a later `UNION` distinct arm, window values are already materialized, and the final `LIMIT/OFFSET` admits only part of the post-distinct stream.

Focused behavior:

- Recursive CTE `LIMIT/OFFSET` rows are traced before compound arm execution.
- `lag()` / `lead()` window values are preserved as part of the compound row signature.
- `UNION` distinct output exposes a current/next yield tape with table-vs-recursive source labels and final-LIMIT admission flags.
- Copied Application `wp_options` rows show plugin/theme option changes moving the admitted final boundary without changing recursive trace rows.

Verification evidence:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext181Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next181.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext181Test.php`
- Result: `1 test files, 248 assertions, 0 failures` with 61 PASS lines.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next181.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +61` from the new focused test file. `benchmarkDenominator.mapped` remains `614 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap:

This avoids accepted next139/157/170/178 compound recursive/window LIMIT surfaces, EXCEPT/INTERSECT variants, limit-zero exhaustion, multi-anchor recursion, SQL expression ORDER BY, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and suite evidence handoffs. The new surface is the yield tape for `UNION ALL` into `UNION` distinct rows after recursive/window materialization and before final LIMIT admission.

Dependency closure:

No new support component is needed. The slice reuses lane-local recursive CTE tracing, window execution, UNION distinct duplicate suppression, compound ORDER BY, and final LIMIT/OFFSET execution.
