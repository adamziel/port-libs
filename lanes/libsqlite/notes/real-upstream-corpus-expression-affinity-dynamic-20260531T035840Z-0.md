# Real Upstream Corpus Expression Affinity Dynamic 20260531T035840Z-0

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T035840Z`
Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported sections: `e_expr-14` LIKE / LIKE ESCAPE semantics and `e_expr-17`
  GLOB / NOT GLOB semantics.

## Coverage Added

- Added `SQLiteRealUpstreamExpressionAffinityLikeGlobExactDynamic20260531Test.php`.
- The test builds a `2016`-case dynamic matrix from:
  - 18 text values including ASCII case pairs, escaped wildcard literals,
    non-ASCII ligatures, numeric text, and empty text;
  - 14 LIKE/GLOB pattern forms from the upstream sections;
  - 8 operator forms: `LIKE`, `NOT LIKE`, `LIKE ... ESCAPE`, `NOT LIKE ...
    ESCAPE`, `GLOB`, and `NOT GLOB`.
- Each case compares parser-level `SQLiteSelectSql` output with local
  `sqlite3` oracle output for `quote(EXPR)`, `typeof(EXPR)`, and `quote(NOT
  (EXPR))`.

## Non-Overlap

This slice does not repeat the accepted real-expression arithmetic/modulo
matrix, e_expr-7 storage-class-only matrix, e_expr-1 precedence matrix,
COLLATE postfix matrix, Unicode GLOB range behavior, JSON table LIKE residual
behavior, or source-neutral CAST/LIKE/GLOB defaults. The owned behavior is
exact parser-level LIKE/GLOB truth and storage parity from upstream
`e_expr.test` sections 14 and 17.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLikeGlobExactDynamic20260531Test.php`
  - `1 test files, 8071 assertions, 0 failures`
  - `2017` focused PASS cases

## Dependency Closure

No new support component is needed. This reuses the existing
`SQLiteSelectSql` parser/executor and the local `sqlite3` oracle path already
used by real upstream expression-affinity dynamic tests.
