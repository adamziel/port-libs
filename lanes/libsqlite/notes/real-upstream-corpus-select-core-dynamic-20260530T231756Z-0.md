# real-upstream-corpus-select-core-dynamic-20260530T231756Z-0

Added `SQLiteRealUpstreamSelect7CaseGroupDynamicTest.php` as an additive real
upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`

Ported upstream scenarios:

- `select7-7.2`: grouped `CASE` arithmetic category expressions with
  `count(*)`.
- `select7-7.4`: grouped `CASE` expressions with mixed text/numeric branch
  shape.
- `select7-7.5`: `typeof(real)` and equality expression projection without
  grouping.
- `select7-7.6`: `typeof(real)` and equality expression projection with
  `GROUP BY`.

Focused coverage:

- `1,001` distinct TestRunner PASS cases.
- `13,073` focused behavior assertions.
- Verified command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect7CaseGroupDynamicTest.php`
  -> `1 test files, 13073 assertions, 0 failures`.

Non-overlap:

- This slice owns the residual `select7.test` grouped CASE/type-affinity
  behavior cluster.
- It does not repeat accepted `select1` through `select6`, `select8`,
  `select9`, `selectA` through `selectH` projection/group/compound batches,
  expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/
  constraint work, or metadata-only runner rows.
- `select7-7.7` text-affinity `HAVING a<b` is excluded from this ready batch
  because the current executor returns an empty rowset for that upstream edge;
  it should be handled as a separate behavior fix if selected.

Mapped denominator:

- Mapped coverage remains unchanged because `select7.test` is already present
  in the hydrated upstream manifest. This handoff should count as PHP PASS-line
  and behavior assertion growth only.

Dependency closure:

- No new support component is needed. This reuses the lane-local
  `SQLiteSelectSql` parser/executor, grouped aggregate, `CASE`, `typeof`, and
  expression evaluation behavior.
