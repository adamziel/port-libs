# real-upstream-corpus-expression-affinity-dynamic-istrue-20260531T070514Z

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T070514Z-0`

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/istrue.test`
- Ported sections: `istrue-100` through `istrue-410` plus `istrue-710`.

Behavior:

- Adds a focused dynamic corpus for `TRUE`/`FALSE` literals, `IS TRUE`,
  `IS FALSE`, `IS NOT TRUE`, `IS NOT FALSE`, `IS NULL`, `IS NOT NULL`,
  OR-composed truth predicates, and `COLLATE` postfix binding around
  truth tests.
- Red-first failure reproduced on `0.5 IS TRUE COLLATE NOCASE`,
  `0.5 IS TRUE COLLATE RTRIM`, and `0.5 IS TRUE COLLATE BINARY`: the port
  returned `0` while upstream SQLite returns `1`.
- Fix keeps `SQLiteSelectSql` parsing `expr IS TRUE COLLATE name` as a
  postfix collation around the truth-test result instead of binding the
  collation to the `TRUE` literal operand.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsTrue20260531T070514ZTest.php`
  - `1 test files, 142 assertions, 0 failures`
  - `64` focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamEExprCollationDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsTrue20260531T070514ZTest.php`
  - `3 test files, 43268 assertions, 0 failures`

Dependency closure:

- No new support component needed. The slice reuses `SQLiteSelectSql`
  expression, predicate, `WHERE`, `ORDER BY`, and collation postfix paths plus
  the local `sqlite3` oracle used by existing real-upstream dynamic tests.

Non-overlap:

- Avoids existing `whereB.test` dynamic affinity coverage and existing
  `e_expr.test` collation matrices. This slice owns selected `istrue.test`
  truth-literal and truth-collation behavior.
