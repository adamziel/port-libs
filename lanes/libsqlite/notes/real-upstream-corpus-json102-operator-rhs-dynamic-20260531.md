# real-upstream-corpus-json102-operator-rhs-dynamic-20260531

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Upstream sections: `json102-1800`, `json102-1801`, `json102-1810`,
  `json102-1811`, `json102-1820`, `json102-1821`, `json102-1830`,
  `json102-1831`

Patch:

- Adds `SQLiteRealUpstreamJson102OperatorRhsDynamicTest.php`.
- Ports the upstream distinction between string RHS operands and integer RHS
  operands for JSON `->` and `->>` operators.
- Expands the behavior over 1,000 deterministic JSON text and JSONB documents.
- Adds 2,001 distinct focused TestRunner PASS cases and 16,003 assertions.

Non-overlap:

- Does not touch JSON table cursor/source/hidden/visible constraint planner
  surfaces.
- Does not repeat `json109` array insert, `json107` legacy BLOB text, or
  `json102` lexical-boundary validity work.
- Uses generic application fixture labels only; no domain-specific API or
  source text is introduced.

Dependency closure:

- No new support component is needed. Existing `SQLiteSelectExpression`,
  `SQLiteJsonB`, and JSON operator path handling are reused.

Focused verification:

- Red-first command before expectation correction:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorRhsDynamicTest.php`
  reported `1 test files, 5003 assertions, 2000 failures`.
- Final focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorRhsDynamicTest.php`
  reported `1 test files, 16003 assertions, 0 failures`.
