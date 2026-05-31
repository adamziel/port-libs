real-upstream-corpus-window-functions-dynamic-20260531T122347Z-0

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported `window1.test` 43.2.2, 43.2.3, and 43.2.4: `sum(b) OVER (ORDER BY a) AS abc` remains usable for `ORDER BY 2`, `ORDER BY abc`, and `ORDER BY abc+5`.

Lane changes:
- Added `SQLiteRealUpstreamWindow1AliasOrderDynamic20260531T122347ZTest.php`.
- Added 1005 focused TestRunner PASS cases and 5005 behavior assertions.
- Updated `lane-status.json` `phpPass` from `2913978` to `2914983`.

Non-overlap:
- This does not repeat the accepted `window1.test` 78/79 empty group_concat and large FOLLOWING frame slice.
- This does not repeat the accepted `window1.test` 52 named-count window slice, RANGE offset slices, window chaining, JSON table windows, grouped SELECT text, or expression ORDER BY-only slices.
- The upstream error-only aliases in `window1.test` 43.1.2, 43.2.5, and 43.2.6 remain excluded because this port targets the allowed alias ordering behavior.

Dependency closure:
- No new support component is required. The tests reuse the existing `SQLiteSelectSql` window materialization and ORDER BY expression evaluation path.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AliasOrderDynamic20260531T122347ZTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AliasOrderDynamic20260531T122347ZTest.php` - 1 file / 5005 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 1 file / 3 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite` - clean.
