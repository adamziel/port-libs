# real-upstream-corpus-json1-jsonb-dynamic-20260530T225341Z-0

- Base accepted HEAD: `6e94a67dd020b9cfec1567bd7fbc6ebe5e036bda`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.
- Added focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamJson102LexicalBoundaryDynamicTest.php`.

Ported upstream sections:

- `json102-1201` and `json102-1202`: ASCII space is valid JSON whitespace, but byte `200` is not.
- `json102-1300` through `json102-1399`: long strings with 50 through 149 embedded quotes round-trip through `json_array()` / `json_extract()` style behavior.
- `json102-1401` through `json102-1415`: leading-zero numeric strict JSON validity versus JSON5 flag validity, plus `json_error_position()` truthiness.
- `json102-1500`: raw control-character string validity accepts only byte `32`.
- `json102-1501`: `json_quote()` escapes bytes `1` through `31` into valid JSON that extracts back to the original text.

Focused evidence:

- First red check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102LexicalBoundaryDynamicTest.php` reported `1 test files, 5875 assertions, 31 failures` because the test had an extra non-upstream `json_error_position()` expectation for raw control bytes inside strings.
- Final check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102LexicalBoundaryDynamicTest.php` reported `1 test files, 5843 assertions, 0 failures`.
- PASS-line movement: `+1033` focused TestRunner PASS cases if accepted as a new selected file.
- Mapped denominator movement: none; mapped inventory is already complete at `1589 / 1589`.

Non-overlap:

- This slice is limited to real upstream `json102.test` lexical boundary sections.
- It does not repeat JSON102 constructor/extract/operator rows, JSON103 aggregate/window behavior, JSON104 merge patch, JSON105/JSON109 mutation path behavior, JSON106/JSON108 invariants, JSON501/JSON502 escaped-label/path behavior, JSONB remove coverage, JSON table cursor/source wiring, hidden/visible constraint pushdown, or metadata-only upstream runner rows.

Dependency closure:

- No new support component is needed. The test reuses existing native `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, `SQLiteJsonQuote`, `SQLiteJsonExtract`, and canonical JSON helpers.

Root harness: not run - isolated micro-slice.
