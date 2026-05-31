# real-upstream-corpus-select-core-dynamic-20260531T001038Z-0

Added `SQLiteRealUpstreamSelectESelect2JoinSemanticsDynamicTest.php` as an additive real upstream SELECT core dynamic corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test`
- Scenario family: `te_join` dataset semantics, including cartesian joins, ON filtering, USING joins, and LEFT JOIN null-extension.

Focused PHP coverage:

- 1 source-citation test.
- 1000 dynamic behavior cases over generic `left_items` and `right_items` row sets.
- Each dynamic case executes four distinct `SQLiteSelectSql` SELECT statements:
  - `CROSS JOIN` row multiplication with deterministic ordering and limit.
  - `JOIN ... ON` composite equality filtering.
  - `JOIN ... USING (tenant_id, item_key)` joined-row behavior.
  - `LEFT JOIN ... ON` unmatched-row SQL NULL extension.
- Focused run: `1 test file / 13004 assertions / 0 failures / 1001 PASS lines`.

Non-overlap:

- This does not repeat accepted `e_select.test` syntax rows, `select1` through `selectH` projection/grouping/compound/alias batches, `selectD` parenthesized nested joins, parser-level JSON table sources, expression `ORDER BY`, grouped SELECT text, or metadata-only runner rows.
- The batch owns `e_select2.test` dataset join semantics through dynamic generic row sets.
- Mapped denominator remains unchanged because `e_select2.test` is already present in the hydrated upstream inventory.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLiteSelectSql` executor and hydrated upstream SQLite checkout as source truth.
- A future separate SELECT name-resolution slice may implement unqualified merged USING-column predicate/projection resolution. This batch keeps predicates/projections qualified where the current executor requires it, while still testing USING join behavior and LEFT JOIN null-extension.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelect2JoinSemanticsDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelect2JoinSemanticsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
