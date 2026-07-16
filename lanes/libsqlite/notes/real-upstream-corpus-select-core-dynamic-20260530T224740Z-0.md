# real-upstream-corpus-select-core-dynamic-20260530T224740Z-0

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Ported real upstream SQLite SELECT expression behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/subselect.test`
- `subselect-2.1`: scalar subqueries honor inner `ORDER BY` direction.
- `subselect-3.2` / `subselect-3.3`: aggregate queries over ordered limited derived subqueries consume the limited order.
- `subselect-3.8` / `subselect-3.9`: scalar subqueries honor `ORDER BY ... LIMIT 1 OFFSET n` in both directions.
- `subselect-4.2`: `IN (SELECT ... ORDER BY ... LIMIT 1)` preserves text-affinity membership behavior.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSubselectOrderLimitDynamicTest.php`.
- 1,001 distinct TestRunner PASS cases.
- 17,004 focused behavior assertions.
- Dynamic generic application row sets vary ordered integer inputs, scalar subquery direction, LIMIT/OFFSET positions, derived aggregate sums, and text-key `IN` subquery membership across 1,000 seeds.

Non-overlap:

- This owns `subselect.test` scalar/derived subquery `ORDER BY` plus `LIMIT/OFFSET` behavior.
- It does not repeat accepted `select1` through `selectH` batches, `selectD` derived aggregate alias handling, `selectE`/`selectF` compound collation/copy behavior, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `subselect.test` is already part of the hydrated upstream SELECT runner-map evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSubselectOrderLimitDynamicTest.php`
  - Result: `1 test files, 17004 assertions, 0 failures`
  - PASS lines: `1001`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, scalar subquery, derived table, aggregate, `ORDER BY`, `LIMIT/OFFSET`, and `IN` predicate execution.
