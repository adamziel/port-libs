# real-upstream-corpus-expression-affinity-dynamic-20260531T155711Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported upstream scenario family: `e_expr-11.2` through `e_expr-11.6`
  host-parameter token syntax and numeric binding assignment.

## Behavior Added

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicNamedParameterSyntax20260531T155711ZTest.php`.
- The focused shard verifies digit-leading `:123`, `@123`, and `$123`
  parameter names, high-bit parameter-name bytes, `$` namespace/suffix forms
  such as `$::::a(++--++)`, `$::a()`, and `$::1(::#$)`, and numeric binding
  assignment for named parameters after `?` and `?NNN` slots.
- `SQLiteSelectSql` now tokenizes SQLite named host parameters using SQLite's
  extended `:`, `@`, and `$...(...)` forms, assigns named parameters numeric
  slots in SQL text order, and preserves `$...(...)` suffixes before comment
  stripping so `--` inside a parameter suffix is not treated as a SQL comment.

## Non-Overlap

- This slice does not repeat accepted nullable `IN` subquery expression
  affinity, unbound-parameter dynamic behavior, e_select GROUP BY collation,
  expression ORDER BY, SELECT subqueries, date localtime, where2 planner,
  walshared checkpoint locking, or temp-schema PRAGMA slices.
- It owns the e_expr-11 extended host-parameter syntax and numbering branch for
  this session.

## Verification

- Red-first before the tokenizer/comment-strip fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNamedParameterSyntax20260531T155711ZTest.php`
  failed with `1 test files, 2899 assertions, 5992 failures`.
- Passing focused run after the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNamedParameterSyntax20260531T155711ZTest.php`
  passed with `1 test files, 14420 assertions, 0 failures`.
- Expected selected-evidence movement: `+7206` focused TestRunner PASS cases
  and `+14420` focused behavior assertions.

## Dependency Closure

- No new support component is needed. The shard reuses the existing bounded
  `SQLiteSelectSql` executor and the local `sqlite3` oracle for real upstream
  expression behavior.
