# real-upstream-corpus-select-core-dynamic-20260531T060517Z-0

Added `SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T060517ZTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Scenario cluster: `e_select-0.3` result-column syntax for `*`, `table.*`, expression result columns, bare aliases, and `AS` aliases.

Coverage:

- `1000` dynamic TestRunner cases plus source/non-overlap/dependency assertions.
- Exercises generic application rows through `SQLiteSelectSql`, with dynamic joins, wildcard projection, `table.*` projection, expression aliases, and explicit qualified result columns.
- The upstream literal-concat alias shape is cited from `e_select-0.3`; this green batch uses `upper(label) AS alias` for the dynamic alias-expression cases because this accepted base still rejects both string-literal concatenation and bare aliases after concatenation in `SQLiteSelectSql` projection expressions.
- No mapped denominator change; `e_select.test` is already in the hydrated upstream inventory.

Non-overlap:

- Avoids accepted grouped SELECT text, SELECT subqueries, expression `ORDER BY`, compound SELECT, JSON table SELECT sources/cursors/constraints, `selectC` alias batches, `selectF` register-copy batches, and previous `e_select` grouped/order dynamic batches.

Dependency closure:

- No new support component is needed. The batch reuses existing lane-local `SQLiteSelectSql` result-column expansion, expression projection, aliases, table-star projection, and join row production.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T060517ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T060517ZTest.php`
- `git diff --check -- lanes/libsqlite`
