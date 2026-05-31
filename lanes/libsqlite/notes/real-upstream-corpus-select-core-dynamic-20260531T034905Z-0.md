# real-upstream-corpus-select-core-dynamic-20260531T034905Z-0

Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.

Added a focused real-upstream SELECT corpus test file for hydrated SQLite
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`.

Covered upstream scenarios:

- `selectD-1.1`: parenthesized comma FROM sources with chained WHERE name resolution.
- `selectD-1.2.1`: nested parenthesized JOIN `ON` scopes.
- `selectD-1.2.2`: inner table projection through nested parenthesized joins.
- `selectD-1.2.3`: table-star projection through nested parenthesized joins.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php`
- Result: `1 test files, 6509 assertions, 0 failures`
- PASS-line growth: `1086` distinct TestRunner PASS cases.

Non-overlap:

- This does not repeat accepted SELECT JOIN text, grouped SELECT text, expression
  ORDER BY, subquery, select4/select7/select8/select9/selectC/selectE/selectF/
  selectH dynamic batches, JSON table SELECT sources, or source-neutral cleanup.
- The accepted base still exposes duplicate `USING(a)` columns under `SELECT *`;
  upstream `selectD` USING coalescing remains a follow-up behavior fix instead
  of being encoded as passing coverage here.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteSelectSql`
  parenthesized source, JOIN `ON`, table-star projection, WHERE predicate, and
  row-array execution support.
