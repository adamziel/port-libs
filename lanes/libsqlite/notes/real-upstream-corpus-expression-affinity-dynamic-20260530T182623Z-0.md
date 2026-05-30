# real-upstream-corpus-expression-affinity-dynamic-20260530T182623Z-0

Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`.

This slice extends `SQLiteRealUpstreamExpressionAffinityDynamicTest.php` with
real SQLite upstream `test/cast.test` behavior:

- `cast-1.1` through `cast-1.69`: BLOB, NULL, integer, real, text, numeric,
  and REAL cast values plus storage-class parity.
- `cast-2.1` through `cast-2.2`: leading whitespace numeric prefixes.
- `cast-3.31`, `cast-4.1` through `cast-4.4`, and `cast-5.1` through
  `cast-5.3`: NULL numeric casts, nonnumeric text casts, integer overflow
  clamping, and exponent handling.
- `cast-6.1`, `cast-7.1` through `cast-7.43`, and `cast-9.1` through
  `cast-9.13`: NUMERIC affinity trimming, signed non-digits, target-affinity
  spelling, and FLEXNUM-style preservation for real numeric values.

Behavior fix:

- `SQLiteSelectExpression` now preserves real storage class for direct
  `CAST(real AS NUMERIC)` and formats real values as SQLite text with a
  visible `.0` for integral real values. This fixes the red upstream cases for
  `CAST(4.0 AS NUMERIC)`, `CAST(4.0 AS TEXT)`, and `CAST(4.0 AS BLOB)`.

Focused delta:

- Previous accepted focused count for this file: `1 test files, 5967
  assertions, 0 failures`.
- After this slice: `1 test files, 10998 assertions, 0 failures`.
- New focused growth: `+223` PASS lines and `+5031` behavior assertions.
- Expected `phpPass` movement: `298721 -> 298944`. Mapped upstream coverage is
  unchanged; this is behavior growth against an already hydrated upstream
  expression/affinity corpus file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
  - `1 test files, 10998 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php | rg -c '^PASS '`
  - `890`
- Pending final lane checks: PHP lint, `git diff --check -- lanes/libsqlite`,
  and the generic no-domain API guard if present.

Dependency closure: no new support component is needed. The slice reuses the
native PHP SELECT expression evaluator, BLOB wrapper, storage-class helpers,
integer prefix parsing, numeric prefix parsing, and CAST target-affinity
normalization.

Non-overlap: avoids accepted `e_expr.test`, `affinity2.test`, `types2.test`,
date affinity, CAST/LIKE/GLOB default cleanup, JSON, VFS/WAL, B-tree, PRAGMA,
trigger, and suite-runner surfaces. The narrower surface is real upstream
`cast.test` expression-affinity CAST behavior and the direct evaluator fix it
exposed.
