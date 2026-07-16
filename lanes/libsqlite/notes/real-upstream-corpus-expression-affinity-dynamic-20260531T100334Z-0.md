# real-upstream-corpus-expression-affinity-dynamic-20260531T100334Z-0

## Scope

- Lane: libsqlite
- Base accepted HEAD: 633d868181ed471ba314711c0ee3aff27a79b97e
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/collate8.test`
- Upstream scenarios: `collate8-3.1` through `collate8-3.5`

## Behavior Ported

SQLite propagates an explicit `COLLATE` operator from nested expression
subtrees when selecting comparison collation. This slice covers the upstream
`collate8.test` function/case propagation cluster:

- nested concatenation operand collation from `collate8-3.1`;
- scalar function argument collation from `upper(...)` in `collate8-3.1`;
- `max(...)` leftmost explicit collation precedence from `collate8-3.2` and
  `collate8-3.3`;
- CASE result-arm leftmost explicit collation precedence from `collate8-3.4`
  and `collate8-3.5`.

Implementation change is limited to `SQLiteSelectPredicate::expressionCollation()`,
which now walks unary/cast operands, binary operands, function argument lists,
row values, and CASE result arms in left-to-right order before comparison.

## Focused Evidence

- Added
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCollate8Propagation20260531T100334ZTest.php`.
- Test count: 1345 distinct TestRunner PASS cases.
- Assertion count: 5383 focused assertions.
- Expected selected `phpPass` movement: `2853307 -> 2854652` (`+1345`).
- Mapped denominator movement: none; `collate8.test` is already in the mapped
  upstream corpus denominator.

## Non-Overlap

This does not repeat the queued or accepted expression-affinity slices for
escape-error parsing, parameter-name syntax, cast encoding, expridx2 write
elision, `e_expr-9` top-level/postfix COLLATE binding, LIKE/GLOB, CASE
truthiness, `types2`/`types3`, or `affinity2`/`affinity3`. The owned gap is
the `collate8-3.x` propagation of explicit collations out of nested expression
trees into comparison collation selection.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql`, `SQLiteSelectExpression`, and `SQLiteSelectPredicate`
parser/executor path plus the local `sqlite3` oracle pattern already used by
real upstream dynamic expression tests.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCollate8Propagation20260531T100334ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCollate8Propagation20260531T100334ZTest.php`
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status.json OK\n";'`
  - `lane-status.json OK`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCollate8Propagation20260531T100334ZTest.php`
  - `1 test files, 5383 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php`
  - `1 test files, 25121 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.
