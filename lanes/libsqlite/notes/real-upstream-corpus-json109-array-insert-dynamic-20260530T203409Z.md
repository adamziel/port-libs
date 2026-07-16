# real-upstream-corpus-json1-jsonb-dynamic-20260530T203409Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- Covered scenarios: `json109-1.1`, `json109-1.2`, `json109-1.3` through `json109-1.9`, and `json109-2.1` through `json109-2.8`.

Patch summary:

- Added `SQLiteRealUpstreamJson109ArrayInsertDynamicBulkTest.php`.
- The focused corpus expands real upstream `json_array_insert()` behavior across text JSON and JSONB inputs:
  positive element indexes, `$[#]` append, `$[#-N]` reverse indexes, too-far reverse indexes that preserve the input, sequential path/value pairs, nested object path array creation, unchanged non-array roots, and invalid array-element path errors.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109ArrayInsertDynamicBulkTest.php`
- Result: `1 test files, 8754 assertions, 0 failures`
- PASS lines: `4457`

Dashboard/counting expectation:

- `phpPass`: `612306 -> 616763` (`+4457`)
- `benchmarkDenominator.mapped`: unchanged at `1472 / 1589`
- Count as PASS-line growth only.

Non-overlap:

- This does not claim JSON501/JSON502, JSON103 aggregate/window, JSON104 merge-patch, JSON106 invariant, JSON table cursor/source/constraint, or existing JSONB remove coverage.
- The upstream file is already present in the hydrated cache; this slice expands `json109.test` array-insert behavior with distinct text/JSONB dynamic cases instead of adding runner metadata rows.

Dependency closure:

- No new support component needed. Existing `SQLiteJsonArrayInsert`, `SQLiteJsonB`, and JSON5 input parsing support are reused.

Root harness:

- Not run - isolated micro-slice.
