# Real Upstream Corpus Expression Affinity Dynamic Follow-up

Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`.

This slice extends `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
with real SQLite upstream `test/types2.test` coverage:

- `types2-2.1` through `types2-2.13`: indexed equality rowsets across
  INTEGER, NUMERIC, TEXT, and no-affinity columns.
- `types2-3.1` through `types2-3.4`: indexed less-than rowsets across the
  same manifest storage classes.
- `types2-6.1` through `types2-6.9`: indexed `IN (...)` rowsets and rowid
  list membership.

Focused delta:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  passed `1 test files, 5557 assertions, 0 failures`.
- After: the same command passed `1 test files, 5967 assertions, 0 failures`.
- New focused growth: `+41` PASS lines and `+410` assertions.

Behavior covered:

- TEXT-affinity indexed probes preserve the difference between inserted integer
  literals and real-looking literals such as `20` versus `20.0`.
- No-affinity indexed probes keep numeric and text storage classes distinct
  for equality, range, and `IN (...)` membership.
- INTEGER and NUMERIC indexed probes apply numeric affinity consistently for
  text and real RHS expressions.

No new support component is needed. The existing
`SQLiteRealExpressionAffinityCorpusPlan` comparison and storage-class behavior
is reused, with an explicit upstream-shaped fixture for `types2.test` manifest
insert values.
