# real-upstream-corpus-upsert-returning-dynamic-20260531T064835Z-0

Added `SQLiteRealUpstreamUpsertReturningExcludedAliasDynamicTest.php` with 1,000 focused TestRunner PASS cases over real upstream SQLite UPSERT behavior.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Scenarios around `upsert4-7.1` through `upsert4-7.4`: a real table named `excluded`, an INSERT target alias, `excluded.column` pseudo-row resolution in assignments and predicates, and a secondary unique conflict target.

The batch is non-overlapping with accepted UPSERT target-first, catch-all, insert-select, target-scope, correlated, autoincrement, trigger-old-value, and excluded-alias guard work. It focuses on the table-name/alias ambiguity path from `upsert4.test` and checks the native `SQLiteUpsertReturningDynamicPlan` row images plus RETURNING stream behavior using generic `w`, `x`, `a_b`, and `z` columns from the upstream script.

No production source change was needed. No new support component is needed; this reuses the existing native UPSERT dynamic planner and RETURNING projection.
