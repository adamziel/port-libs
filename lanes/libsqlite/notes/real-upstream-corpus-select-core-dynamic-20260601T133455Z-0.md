# real-upstream-corpus-select-core-dynamic-20260601T133455Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260601T133455Z-0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`
- Ported sections: `selectA-2.37`, `selectA-2.38`, and `selectA-2.39`.
- Behavior: `SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY c...`
  resolves final compound `ORDER BY c` against the right-arm result expression
  but still sorts the first-arm output column with that expression's inherited
  `NOCASE` collation.

## Implementation

- `SQLiteSelectSql::compoundOrderBy()` now receives complete compound arm
  plans, not only select lists.
- Matched compound `ORDER BY` result columns now carry an inherited collation
  when the matching select expression is backed by source-column collation
  metadata and the `ORDER BY` term does not declare an explicit `COLLATE`.
- Explicit `COLLATE` on the `ORDER BY` term still wins.

## PHP Coverage

- Added `SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php`.
- New focused PASS cases: `1003`.
- New focused assertion count: `18024`.
- The dynamic batch expands the three upstream orderings across 1000 generic
  mixed-case source-table variants with table metadata for `c`/`z` `NOCASE`
  collations.

## Red-First Evidence

- Command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php`
- Before the source fix: `1 test files, 1010 assertions, 1001 failures`.
- Failure mode: `selectA-2.37` sorted the reversed `UNION` result using binary
  text order (`B`, `D`, `M`, `a`, ...) instead of upstream `NOCASE` order
  (`a`, `B`, `c`, ...).

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php`
  - Result: `1 test files, 18024 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectAReversedUnionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionDistinctOrderRemainderDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php`
  - Result: `4 test files, 45562 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 6 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: clean.

## Non-Overlap

This owns only the previously skipped `selectA-2.37` through `selectA-2.39`
reversed `UNION` collation-inheritance cluster. It avoids accepted
`selectA-2.31` through `selectA-2.36`, `selectA-2.40`, union-all selectA,
left-arm selectA unions, select9 set operators, selectB compound subqueries,
selectD parenthesized joins, JSON table, WAL, B-tree, VFS, and source-neutral
cleanup slices.

Mapped denominator coverage remains unchanged because `selectA.test` is
already present in the hydrated upstream inventory. Expected selected
PASS-line movement is `+1003`.

## Dependency Closure

No new support component is needed. This reuses existing lane-local
`SQLiteSelectSql`, `SQLiteSelectExpression::collation()`, compound SELECT, and
row metadata collation support.

Root harness status: not run - isolated micro-slice.
