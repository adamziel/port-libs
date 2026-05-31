# real-upstream-corpus-select-core-dynamic-20260531T073737Z-0

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.

Added a focused real-upstream SELECT core dynamic batch for SQLite upstream
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`.

Owned upstream scenarios:

- `select1-10.2`: projected alias referenced by unary `ORDER BY -x`.
- `select1-10.3`: projected alias referenced inside `ORDER BY abs(x)`.
- `select1-10.4`: projected alias referenced inside `ORDER BY -abs(x)`.
- `select1-10.6`: projected aliases referenced by `WHERE x>0 AND y<50`.

Focused movement:

- New PHP test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAliasOrderWhere20260531Test.php`.
- Distinct TestRunner PASS cases: `1002`.
- Behavior assertions: `4009`.
- Expected `phpPass` movement if accepted alone: `2717884 -> 2718886`.
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`.

Non-overlap:

- Does not touch accepted SELECT expression `ORDER BY`, grouped SELECT SQL,
  subquery execution, JSON table sources/constraints, B-tree, VFS, WAL,
  source-neutral API work, or numbered helper consolidation.
- Existing corpus files already cover `select1-10.1`, `select1-10.5`, and
  `select1-10.7`; this slice fills the unclaimed alias-resolution cases
  around `select1-10.2` through `select1-10.6`.

Dependency closure:

- No new support component is needed. The batch reuses existing
  `SQLiteSelectSql`, `SQLiteSelectExpression`, scalar `abs()`, alias
  projection, and row-array executor behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAliasOrderWhere20260531Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAliasOrderWhere20260531Test.php`
  - `1 test files, 4009 assertions, 0 failures`
  - `1002` PASS lines
