# real-upstream-corpus-btree-index-dynamic-20260531T071524Z-0

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`.

Added a focused real-upstream B-tree/index expression corpus slice from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr2.test`
sections `indexexpr2-6.1.1` through `indexexpr2-11.0`.

Covered scenarios:

- CAST expression indexes over INTEGER and TEXT lookup values.
- ABS expression-index integer-overflow cleanup with no schema residue.
- NULL partial-index truth-expression cases from nullable `BETWEEN`.
- LEFT JOIN nullable truth expressions that must keep null-extended rows.
- Expression-index aggregate and collation rewrite regressions.
- Generated-column expression-index aggregate resolution across inner and outer loops.

Focused movement:

- New test file: `SQLiteRealUpstreamBtreeIndexExpr2CastTruthAggregateDynamicTest.php`.
- New focused PASS lines: `1003`.
- New focused assertions: `16507`.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  countable as PASS-line/assertion growth, not mapped-denominator growth.

Non-overlap:

- Existing accepted B-tree/index expression coverage already handles
  `indexexpr2-3.4.5/3.4.6` collation ORDER BY and `indexexpr2-4.110` through
  `4.130` update refcount behavior. This slice starts at `indexexpr2-6.1.1`
  and covers later CAST/truth/aggregate/generation regressions.
- This does not repeat accepted B-tree page moves, overflow freelist release,
  e_reindex, index8 ORDER BY LIMIT, index9 bound partial-index, or existing
  indexexpr1/indexexpr3 coverage.

Dependency closure:

- No new support component needed. The slice reuses lane-local expression-index,
  affinity, nullable truth-expression, aggregate, collation, and generated-column
  planning helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2CastTruthAggregateDynamicTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2CastTruthAggregateDynamicTest.php`
  - `1 test files, 16507 assertions, 0 failures`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
  - `1 test files, 65167 assertions, 0 failures`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExprDynamicTest.php`
  - `1 test files, 34275 assertions, 0 failures`
