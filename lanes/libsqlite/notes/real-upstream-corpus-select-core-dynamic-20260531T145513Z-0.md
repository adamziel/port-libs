# real-upstream-corpus-select-core-dynamic-20260531T145513Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T145513Z-0`

Base accepted HEAD: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported scenarios: `e_select-7.1` through `e_select-7.12`.

Behavior covered:

- Compound SELECT arms reject mismatched result-column counts before row production.
- `ORDER BY` and `LIMIT` placement is rejected on non-final compound arms, and a final `VALUES(...)` arm rejects a trailing `ORDER BY`/`LIMIT`.
- `UNION ALL`, `UNION`, `INTERSECT`, and `EXCEPT` preserve SQLite duplicate handling, NULL equality, and left-to-right grouping.
- Compound set comparison now derives explicit `COLLATE` terms by output ordinal across arms, so right-arm explicit `NOCASE` participates when the left arm has no explicit collation, while an explicit left `BINARY` remains authoritative.
- Compound duplicate elimination now retains the latest duplicate row value for a folded set key, matching `e_select-7.10`.
- Compound set comparison does not apply column affinity transformations between integer/real/text values.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundCoreDynamic20260531T145513ZTest.php`.
- New TestRunner PASS-line growth is `1002` distinct cases.
- Focused new-test run produced `1 test files, 79010 assertions, 0 failures`.
- Red-first check before the source fix failed `1000 / 1002` cases because compound width checks were deferred until rows existed, `VALUES(...) ORDER BY` was accepted, right-arm explicit collation was ignored for set comparison, and collated UNION duplicate rows retained the old value.

Non-overlap:

- This slice owns only `e_select.test` section 7 compound SELECT core semantics.
- It avoids accepted e_select DISTINCT/ALL, empty aggregates, ORDER BY collation/resolution, LIMIT datatype/comma LIMIT, e_select2 joins, selectA/select9 order sweeps, grouped SELECT text, JSON table, B-tree, WAL, VFS, PRAGMA, trigger, and metadata-only runner rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundCoreDynamic20260531T145513ZTest.php`
  - Result: `1 test files, 79010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCompoundCollationTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9SetOpsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionOrderDynamicTest.php lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php`
  - Result: `5 test files, 68268 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. This slice reuses `SQLiteSelectSql` compound planning and `SQLiteSelectCompound` set comparison against the hydrated upstream SQLite `e_select.test` corpus.
