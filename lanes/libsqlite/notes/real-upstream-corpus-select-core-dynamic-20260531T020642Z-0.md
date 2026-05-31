# real-upstream-corpus-select-core-dynamic-20260531T020642Z-0

Added `SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-1.1`: parenthesized comma FROM terms preserve name resolution.
- `selectD-1.2.1`, `selectD-1.2.2`, and `selectD-1.2.7`: nested parenthesized JOINs preserve inner-table and alias name resolution.
- `selectD-1.5` and `selectD-1.6`: parenthesized LEFT JOIN/USING groups preserve matched rows and NULL extension.
- The same upstream cases are repeated by `selectD-2.*` with query flattener disabled; the PHP port exercises both shapes through dynamic row sets rather than planner toggles.

Focused PHP coverage:

- 1,207 distinct TestRunner PASS cases.
- 5,792 focused behavior assertions.
- Dynamic generic application row sets vary base ids, offset chains, schema-qualified table references, aliases, parenthesized comma sources, nested joins, and matched/missing LEFT JOIN payloads over 240 seeds.

Implementation movement:

- `SQLiteSelectSql` now accepts schema-qualified column identifiers with more than one dot in expressions and wildcard prefixes.
- `SQLiteSelectExpression` and `SQLiteSelectPredicate` resolve schema-qualified column names like `main.app_tail.a` and `temp.app_right.a` to the alias-qualified row keys already materialized by the SELECT executor.

Non-overlap:

- This slice owns the `selectD.test` parenthesized FROM/JOIN name-resolution cluster and does not repeat accepted `selectC` alias behavior, `select8` LIMIT/OFFSET, compound SELECT, grouped SELECT text, expression ORDER BY, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because the upstream inventory is already complete.
- Two `selectD.test` shapes remain for follow-up: schema-qualified table-star expansion and one duplicate schema-name full-projection join form.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php`
  - Result: `1 test files, 5792 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The batch reuses the existing native SELECT executor, join planner, predicate evaluator, and expression evaluator.
