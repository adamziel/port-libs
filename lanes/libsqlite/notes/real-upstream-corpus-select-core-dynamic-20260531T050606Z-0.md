# Real upstream SELECT core dynamic selectG scalar VALUES

Added `SQLiteRealUpstreamSelectGValuesScalarDynamicTest.php` as an additive real
upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `selectG-110`: a multi-row `VALUES` clause inside a scalar SELECT expression
  returns the left-most row.
- `selectG-120`: only the left-most row of that scalar `VALUES` expression is
  needed.

Focused local coverage:

- `1002` distinct TestRunner PASS cases.
- `5008` focused behavior assertions.
- Command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectGValuesScalarDynamicTest.php`
- Result: `1 test files, 5008 assertions, 0 failures`.

Non-overlap:

- This owns the residual `selectG.test` scalar multi-row `VALUES` expression
  behavior.
- It does not repeat accepted `select1` through `select9`, `selectA` through
  `selectF`, `selectH`, selectD parenthesized join/derived aggregate batches,
  grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/
  constraint work, WAL/VFS/B-tree slices, or metadata-only runner rows.
- Mapped denominator coverage remains unchanged because `selectG.test` is
  already present in the hydrated upstream inventory.

Dependency closure:

- No new support component is needed. The existing `SQLiteSelectSql` scalar
  expression path already implements the upstream left-most scalar `VALUES`
  behavior against generic application rows.
