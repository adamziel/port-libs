# real-upstream-corpus-expression-affinity-dynamic-20260531T075022Z-0

Added `SQLiteRealUpstreamExpressionAffinityIntRealDynamic20260531Test.php` as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/intreal.test`
- Scenario ranges: `intreal-2.1` through `intreal-2.6` and `intreal-4.0` through `intreal-4.3`.

Focused coverage:

- 3,000 distinct dynamic TestRunner cases plus one ownership/provenance case.
- 3,008 focused assertions.
- Large integer values inserted through a REAL-affinity column.
- Equality and range predicates against `CAST(<integer> AS REAL)`.
- Reversed equality, `BETWEEN`, non-less/non-greater, arithmetic identity wrappers, `typeof()` and `substr()` result projections.
- Native PHP execution uses `SQLiteSelectSql` over row arrays with `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()`.
- Expected rows are generated from a local sqlite3 oracle against the same real upstream behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityIntRealDynamic20260531Test.php`
  - `1 test files, 3008 assertions, 0 failures`

Non-overlap:

- This owns the `intreal.test` large-integer REAL-affinity comparison/range branch for this session.
- It does not repeat accepted REAL arithmetic, REAL NaN, CAST prefix, signed literal, types2/types3, affinity2/affinity3 joins, expression `ORDER BY`, boolean truthiness, planner hints, LIKE/GLOB, MATCH/REGEXP, date affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.

Excluded follow-up:

- Joined row production over the same `intreal.test` values exposed a separate comma-join projection metadata issue in this executor path.
- Huge REAL division text formatting differs from sqlite3 at the final digit for some values. This slice excludes that arithmetic-output surface instead of weakening oracle expectations.

Dependency closure:

- No new support component is needed. The batch reuses the existing bounded `SQLiteSelectSql` executor, REAL insert-affinity helper, and local sqlite3 oracle pattern already used by adjacent real upstream expression tests.
