# real-upstream-corpus-expression-affinity-dynamic-20260530T222355Z-0

Base accepted HEAD: `9f789d799d368a95f9314c9ed366646dd5d17143`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test`
- Scenario ranges: `expr2-1.1`, `expr2-1.2.1`, `expr2-1.2.2`, `expr2-1.3`, `expr2-1.4.1`, and `expr2-1.4.2`.

Focused behavior:

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpr2Test.php`.
- Covers SQLite truth-value expression behavior through parser-level `SQLiteSelectSql` execution: `IS FALSE`, `IS NOT FALSE`, `NOT`, `OR`, comparison inside truth-value expressions, projection evaluation, and `WHERE` filtering.
- Runs the real upstream expressions over 168 dynamic `t0.c0` row values, producing 1,008 distinct focused TestRunner PASS cases and 3,024 assertions.

Evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpr2Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpr2Test.php`
  - `1 test files, 3024 assertions, 0 failures`
  - `1008` focused PASS cases

Expected movement:

- `phpPass`: `948261 -> 949269` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP `SQLiteSelectSql`, `SQLiteSelectPredicate`, and expression truthiness handling.

Non-overlap:

- Does not touch JSON, WAL, pager, VFS, B-tree, trigger/FK, PRAGMA, date/time, suite admission, cast-target affinity, `types2.test`, LIKE/GLOB, direct affinity comparison helpers, or expression operator corpus surfaces.
- The added behavior is the `expr2.test` truth-value composition cluster only.
