# real-upstream-corpus-expression-affinity-dynamic-20260601T021933Z-0

Added `SQLiteRealUpstreamExpressionAffinityInListDynamicTest.php` as an
additive real upstream expression/affinity dynamic corpus batch.

Source truth from hydrated upstream SQLite:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-200..300`: column affinity, BLOB/no-affinity columns, and
    unary-plus affinity stripping in comparison expressions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-1.*`: equality comparison across TEXT, NUMERIC, and no-affinity
    columns.
  - `types2-4.*`: greater-than comparison after TEXT affinity converts numeric
    literals to text.
  - `types2-5.*`: `IN (...)` expression-list semantics where the left operand
    affinity applies and right-side column affinity is ignored.

Focused coverage:

- 1001 new focused PASS cases.
- 11204 behavior assertions.
- `phpPass` delta: 5357651 -> 5358652.
- Non-overlap: existing expression-affinity dynamic coverage already covered
  `expr.test` arithmetic, bitwise, real arithmetic, simple comparison, CAST
  storage, and NULL arithmetic. This slice isolates `affinity2.test` and
  `types2.test` column-affinity comparison and `IN` expression-list behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListDynamicTest.php`
  - `1 test files, 11204 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListDynamicTest.php`
  - `2 test files, 36604 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListDynamicTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses existing native
  `SQLiteAffinityComparison` and `SQLiteSelectPredicate` behavior.
