# real-upstream-corpus-expression-affinity-dynamic-20260530T225128Z-0

Added `SQLiteRealUpstreamTypes3TextDualRepresentationDynamicTest.php` as an
additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`
- Scenario ranges: `types3-3.1` through `types3-3.5`.

Behavior covered:

- TEXT PRIMARY KEY comparisons must use the coerced TEXT representation of RHS
  values even when the RHS originated from an integer or real-style carrier.
- The dynamic matrix expands the upstream `upper(1)`, `add_text_type(1)`,
  `add_int_type('1')`, `add_real_type('1.25')`, and `add_text_type(1.25)`
  comparison cases across 240 stored TEXT values.
- This is non-overlapping with the existing `types2.test` indexed affinity,
  `affinity3.test` REAL view/USING join, `expr2.test` boolean truth, cast-only,
  LIKE/GLOB, and direct scalar comparison batches.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes3TextDualRepresentationDynamicTest.php`
- Result: `1 test files, 8404 assertions, 0 failures`
- PASS-line movement: `+1201` distinct focused TestRunner PASS cases.
- Mapped denominator movement: unchanged; mapped inventory is already complete
  and this slice claims behavior/PASS growth only.

Dependency closure:

- No new support component is needed. The slice reuses native
  `SQLiteRealExpressionAffinityCorpusPlan` and `SQLiteAffinityComparison`
  comparison/coercion helpers.
