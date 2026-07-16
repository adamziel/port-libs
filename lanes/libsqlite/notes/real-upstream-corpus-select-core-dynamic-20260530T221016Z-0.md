# real-upstream-corpus-select-core-dynamic-20260530T221016Z-0

Added `SQLiteRealUpstreamSelect9SetOpsDynamicTest.php` as an additive real
upstream SELECT core corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`
- `select9-1.7` through `select9-1.11`: `UNION` distinct result ordering,
  explicit `ORDER BY`, and upstream `test_compound_select` LIMIT/OFFSET
  windows.
- `select9-1.13` and `select9-1.14`: `INTERSECT` ordering by second-column
  terms plus LIMIT/OFFSET windows.
- `select9-1.18` through `select9-1.20`: `EXCEPT` ordering by first/second
  result columns plus LIMIT/OFFSET windows.

Behavior change:

- `SQLiteSelectSql` now applies SQLite's implicit set-order over all output
  columns for compound SELECTs that use `UNION`, `INTERSECT`, or `EXCEPT`.
  This ordering happens before any final explicit `ORDER BY`, preserving
  SQLite's deterministic tie order while leaving `UNION ALL` arm order
  unchanged.

Focused coverage:

- New focused test file contributes 2,109 distinct TestRunner PASS cases and
  8,447 assertions.
- Before the fix, the new corpus exposed 800+ failures around `UNION` distinct
  set-order and tie ordering under explicit `ORDER BY`.
- After the fix, the focused run passes with `1 test files, 8447 assertions,
  0 failures`.

Non-overlap:

- This owns the residual `select9.test` set-operation ordering cluster and
  does not repeat the existing `select9` `UNION ALL` batch, accepted
  select1/select2/select3/select4/select5/select6/select7/select8/selectA/
  selectB/selectC/selectD/selectE/selectF/selectG/selectH batches, grouped
  SELECT text, expression `ORDER BY`, JSON table SELECT sources, or
  metadata-only runner rows.
- Mapped denominator remains unchanged because `select9.test` is already
  present in the hydrated upstream inventory.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect9SetOpsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9SetOpsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCompoundCollationDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed; this reuses the existing bounded
  `SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteSelectResult`
  executor components.
