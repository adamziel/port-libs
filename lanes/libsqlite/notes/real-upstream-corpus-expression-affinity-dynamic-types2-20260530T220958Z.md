# Real Upstream Corpus Expression Affinity Dynamic Types2 Follow-up

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T220958Z-0`

Base accepted HEAD: `982e8dd8663ac2abd3a38d17e45a83e32b2f3371`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Scenario ranges: `types2-2.*`, `types2-3.*`, `types2-4.*`, `types2-5.*`, `types2-6.*`, plus adjacent dynamic `BETWEEN` checks over the same `t2` affinity matrix.

Focused behavior:

- Adds `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes2Test.php`.
- Covers dynamic row filtering through `SQLiteSelectPredicate::filter()` with column affinity metadata for INTEGER, NUMERIC, TEXT, and NONE affinity columns.
- Exercises equality, inequality, ordered range operators, IN, NOT IN, BETWEEN, and NOT BETWEEN over the upstream `10`, `10.0`, `'10'`, `'10.0'`, `20`, `20.0`, `'20'`, `'20.0'`, `30`, `30.0`, `'30'`, and `'30.0'` matrix.

Evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes2Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes2Test.php`
  - `1 test files, 5260 assertions, 0 failures`
  - 1164 focused PASS cases

Expected movement:

- `phpPass`: `911920 -> 913084` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component is needed; the slice reuses existing native `SQLiteSelectPredicate`, `SQLiteRealExpressionAffinityCorpusPlan`, and affinity comparison helpers.

Non-overlap:

- Does not touch JSON, WAL, pager, VFS, B-tree, trigger, PRAGMA, date, or suite-admission surfaces.
- Extends the expression-affinity dynamic corpus with `types2.test` row-filter predicate behavior rather than repeating existing cast-only, scalar expression, LIKE/GLOB, or direct comparison helper assertions.
