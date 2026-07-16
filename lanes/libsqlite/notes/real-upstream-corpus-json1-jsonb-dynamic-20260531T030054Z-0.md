# real-upstream-corpus-json1-jsonb-dynamic-20260531T030054Z-0

## Scope

- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  sections `json102-1600`, `json102-1610`, `json102-1620`, and
  `json102-1800..1831`, plus
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
  section `jsonb01-2.0`.
- Behavior fixed: parser-level SELECT expressions now preserve `->` and
  `->>` as JSON operators instead of splitting at the `-` as arithmetic
  subtraction before the JSON operator group is reached.
- Production change: `SQLiteSelectSql::topLevelExpressionOperator()` skips
  `-` when it begins a JSON operator token.

## Red/Green Evidence

- Before fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorJsonbDynamicTest.php`
  reported `1 test files, 2616 assertions, 1008 failures`. The repeated
  failure was `SQLite SELECT SQL expression > needs both operands`.
- After fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorJsonbDynamicTest.php`
  reported `1 test files, 8178 assertions, 0 failures`.

## Non-Overlap

This does not add metadata rows or repeat accepted JSON constructor, mutation,
table cursor/source, hidden/visible constraint, aggregate/window, JSON109
array-insert, JSON101 edit-cache, JSON105 reverse-index, JSON106 invariant, or
JSON501/502 JSON5 coverage. It fixes the current-base parser blocker for the
existing real upstream JSON102/JSONB operator corpus.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteSelectSql`, `SQLiteSelectExpression`, JSON extraction, JSON inspection,
and JSONB helpers.
