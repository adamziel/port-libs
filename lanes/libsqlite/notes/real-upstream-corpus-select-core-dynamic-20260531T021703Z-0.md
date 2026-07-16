# real-upstream-corpus-select-core-dynamic-20260531T021703Z-0

Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`.

Ported real upstream SQLite SELECT-core behavior from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`:

- `select2-1.1` and `select2-1.2`: nested SELECT iteration over
  `SELECT DISTINCT f1 FROM tbl1 ...` with inner ordered `SELECT f2 ...`.
- `select2-4.1` through `select2-4.5`: scalar `max(a,b)` / `min(a,b)`,
  truthy `WHERE b`, `NOT min(a,b)`, comma joins, and `CROSS JOIN` row
  production.

Focused PHP coverage added in
`lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedWhereDynamicTest.php`:

- 1 source-provenance PASS case.
- 1 canonical nested cursor PASS case.
- 350 dynamic nested distinct/inner cursor PASS cases.
- 700 dynamic scalar-WHERE join PASS cases.
- 10,512 focused assertions, 1,052 distinct TestRunner PASS cases.

Non-overlap:

- Avoids accepted `select1`, `select8`, `select9`, `selectE`, and `selectF`
  corpus files already present in nearby SELECT dynamic tests.
- Avoids accepted JOIN text dispatch, GROUP BY/HAVING text, expression
  ORDER BY, comma LIMIT, JSON table SELECT source/cursor/constraint work, and
  storage/VFS/WAL/B-tree clusters.
- Does not add WordPress-specific API or fixture names.

Exclusion:

- Upstream `select2-4.6` and `select2-4.7` CASE predicate rows are not ported
  in this slice. Current `SQLiteSelectSql` rejects `CASE WHEN ... THEN ... END`
  in WHERE predicates as an unterminated CASE expression, so CASE predicate
  parser support remains a follow-up behavior slice.

Dependency closure:

- No new support component is needed. The slice reuses the existing
  `SQLiteSelectSql` row-array executor and hydrated upstream SQLite test
  checkout as source truth.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedWhereDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedWhereDynamicTest.php`
  passed: `1 test files, 10512 assertions, 0 failures`.
- Root harness not run: isolated micro-slice.
