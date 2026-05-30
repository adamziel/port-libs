# real-upstream-corpus-expression-affinity-dynamic-20260530T203549Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T203549Z-0`

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Ported section family:
  - `types2-2.*` through `types2-4.*`: indexed and non-indexed comparison
    rowsets over manifest typing, specifically the no-affinity `o XBLOBY`
    column from upstream table `t2`.

## Behavior

- Added `SQLiteRealUpstreamExpressionAffinityDynamicTypes2BlobMatrixTest.php`.
- The focused corpus creates the real upstream `t2(i INTEGER, n NUMERIC, t TEXT,
  o XBLOBY)` shape with generic row-array data.
- It compares the PHP `SQLiteSelectSql` rowset result against local `sqlite3`
  for the `o` column across:
  - 8 comparison spellings: `=`, `==`, `<`, `<=`, `>`, `>=`, `!=`, and `<>`;
  - 156 integer/text literals selected from the upstream numeric range plus
    leading-zero and decimal-looking text values.
- Total focused growth: 1249 distinct TestRunner PASS cases / 1252 assertions.

## Non-Overlap

This does not repeat accepted `affinity2.test` column-comparison coverage,
`types2.test` INTEGER/NUMERIC/TEXT dynamic matrix coverage, expression NULL
comparison, BETWEEN matrix, expression precedence/operator corpus, date
affinity, B-tree numeric affinity, Unicode GLOB, or source-neutral
CAST/LIKE/GLOB defaults. The owned gap is broad sqlite3-oracle rowset coverage
for the real upstream no-affinity `XBLOBY` comparison column.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2BlobMatrixTest.php`
  - `1 test files, 1252 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing row-array
SELECT executor, column affinity metadata,
`SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()`, and local
`sqlite3` oracle pattern already used by real upstream expression-affinity
dynamic tests.
