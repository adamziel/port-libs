# real-upstream-corpus-upsert-returning-dynamic-20260531T072130Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-15.0` through `returning1-15.2`: REAL values that can be represented as integers retain REAL affinity through `INSERT`, `UPDATE`, and `DELETE ... RETURNING`.

Implementation:

- Extended the bounded native `SQLiteUpsertReturningSql` parser/executor to admit floating-point SQL literals, including decimal and scientific notation forms.
- Preserved REAL arithmetic for `DO UPDATE SET real_value = real_value + ...` when either operand is a float, instead of coercing the result through integer arithmetic.
- Added `SQLiteRealUpstreamUpsertReturningRealAffinityDynamicTest.php`, which ports the REAL-affinity RETURNING behavior into an UPSERT/RETURNING dynamic corpus over generic `app_real_metric` rows.

Focused count:

- 504 focused TestRunner PASS cases.
- 1510 focused assertions.

Non-overlap:

- This avoids accepted `upsert2`, `upsert4`, `upsert5`, omitted-target, catch-all, partial-predicate, target-alias, excluded-alias, explicit-rowid/autoincrement, trigger/FK RETURNING, row-value RETURNING, and parser-level SELECT-input UPSERT matrices.
- This slice owns only the REAL-literal and REAL-arithmetic affinity edge for the existing bounded UPSERT/RETURNING executor, backed by upstream `returning1.test` section 15.

Dependency closure:

- No new support component is needed. The patch reuses existing native PHP UPSERT conflict handling and RETURNING projection; the only support gap was the bounded SQL literal/arithmetic evaluator preserving REAL values.
