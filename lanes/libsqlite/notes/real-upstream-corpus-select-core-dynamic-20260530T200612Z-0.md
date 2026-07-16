# real-upstream-corpus-select-core-dynamic-20260530T200612Z-0

Added `SQLiteRealUpstreamSelectGLargeValuesDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `selectG-100`: large `VALUES` input supports `count()`, `sum()`, and `avg()`.
- `selectG-110` and `selectG-120`: multi-valued `VALUES` in scalar contexts remains bounded and uses the left-most row.

Focused behavior:

- 1 source-citation case plus 1,000 distinct dynamic TestRunner cases.
- Each dynamic case executes a bounded `VALUES` source through the native PHP SELECT executor, checks aggregate `count`/`sum`/`avg`, then checks filtered `ORDER BY ... LIMIT` output over the same VALUES input.
- The generated row windows vary start value, row count, step, threshold, and limit while staying tied to the real upstream `selectG.test` large-VALUES behavior.

Non-overlap:

- This does not repeat accepted `select1`-`select9`, `selectA`-`selectF`, alias, grouped SELECT text, expression ORDER BY, subquery, JSON table source/cursor/constraint, WAL/VFS/B-tree, or metadata-only runner evidence.
- No mapped denominator change; `selectG.test` is already present in the hydrated upstream SELECT corpus inventory.

Dependency closure:

- No new support component is needed. The batch reuses existing lane-local `SQLiteSelectSql` `VALUES` source, aggregate, filter, order, and limit execution.
