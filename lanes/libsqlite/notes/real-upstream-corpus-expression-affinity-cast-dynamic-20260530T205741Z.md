# Real Upstream Corpus Expression Affinity Cast Dynamic

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T205741Z-0`

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`

Ported scenarios:

- `cast.test` `cast-1.1..1.69`: BLOB/TEXT/NUMERIC/INTEGER/REAL CAST result values and storage classes.
- `cast.test` `cast-2.1..2.2`: leading-space integer and REAL casts.
- `cast.test` `cast-3.1..3.32`: int64 boundary and BLOB numeric boundary casts.
- `cast.test` `cast-5.1..5.3`: integer clamp and exponent-prefix behavior.
- `cast.test` `cast-7.1..7.43`: sign-only, dot, zero, and exact REAL-to-NUMERIC edge behavior.
- `cast.test` `cast-9.1..9.13`: NUMERIC result storage classes through joined/subquery-style materialization.
- `cast.test` `cast-10.1..10.10`: FLEXNUM-style REAL preservation across compound/value-row inputs.
- `affinity2.test` `affinity2-110..150`: insertion affinity storage class preservation.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php`
- Result: `1 test files, 1441 assertions, 0 failures`.

Non-overlap:

- This does not repeat the accepted `SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php` arithmetic/operator matrix or the earlier `affinity2-200..300` equality cases.
- This slice targets CAST result/storage behavior plus insertion-affinity storage classes from real upstream files.

Dependency closure:

- No new support component is needed. The tests reuse the existing native `SQLiteRealExpressionAffinityCorpusPlan`, `SQLiteBlobValue`, and affinity/storage-class helpers.
