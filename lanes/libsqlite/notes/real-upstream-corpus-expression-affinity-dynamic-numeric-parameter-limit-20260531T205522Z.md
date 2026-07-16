# real-upstream-corpus-expression-affinity-dynamic-20260531T205522Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported upstream scenario family: `e_expr-11.1`, `e_expr-11.3`, and the
  high-water assignment edge behind `e_expr-11.7`.
- Local oracle evidence: `sqlite3 :memory: 'PRAGMA compile_options;'` reports
  `MAX_VARIABLE_NUMBER=32766`.

## Behavior Added

- `SQLiteSelectSql` now enforces SQLite's explicit numeric host-parameter
  bounds for `?NNN`, including leading-zero forms, zero, oversized integer
  text, and values above the local upstream maximum.
- The SELECT binder now rejects implicit `?` and newly assigned named
  parameters once the largest preceding slot is `?32766`, matching upstream's
  `too many SQL variables` behavior.
- Repeated named tokens assigned before the maximum slot remain valid.

## Focused Tests

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicNumericParameterLimit20260531T205522ZTest.php`.
- The shard contributes `2735` distinct focused TestRunner PASS cases and
  `2742` behavior assertions.
- It covers `800` valid explicit numeric slots, `128` leading-zero explicit
  slots, `256` valid implicit/named high-water follow-up slots, `1024`
  over-limit explicit slots, the exact large invalid numeric strings from
  upstream `e_expr-11.1`, `512` implicit/named overflow cases, and the repeated
  named-token-before-max edge.

## Evidence

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteSelectSql; foreach (["SELECT ?250000", "SELECT ?250001", "SELECT ?0", "SELECT ?9223372036854775808"] as $sql) { try { $rows=SQLiteSelectSql::execute($sql, [], [250000=>7,250001=>8]); echo $sql, " => OK ", json_encode($rows), "\n"; } catch (Throwable $e) { echo $sql, " => ", get_class($e), ": ", $e->getMessage(), "\n"; } }'`
  showed `SELECT ?250000`, `SELECT ?250001`, and
  `SELECT ?9223372036854775808` were incorrectly accepted.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNumericParameterLimit20260531T205522ZTest.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNumericParameterLimit20260531T205522ZTest.php`:
  `1 test files, 2742 assertions, 0 failures`
- Adjacent regression:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNamedParameterSyntax20260531T155711ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityUnboundParameterDynamicTest.php`:
  `3 test files, 69394 assertions, 0 failures`
- API guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `1 test files, 3 assertions, 0 failures`

## Non-Overlap

- This slice extends the explicit numeric `?NNN` maximum and parameter
  high-water rejection branch beyond accepted e_expr-11 bound-value,
  unbound-NULL, named-token syntax, and named-numbering shards.
- It avoids CASE, IN, BETWEEN, min/max, cast/numcast, JSON, WAL, VFS, B-tree,
  date, PRAGMA, and planner clusters already covered in the accepted tree.

## Dependency Closure

- No new support component is needed. The shard reuses the existing
  `SQLiteSelectSql` parser-level host-parameter binder and local `sqlite3`
  compile-option evidence for the hydrated upstream `e_expr.test` behavior.
