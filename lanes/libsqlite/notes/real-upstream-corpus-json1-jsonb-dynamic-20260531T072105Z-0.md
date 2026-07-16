# real-upstream-corpus-json1-jsonb-dynamic-20260531T072105Z-0

Base accepted HEAD: `9d0b0fe07345f3693373fb79bddfe1aa2564a7a2`.

Added `SQLiteRealUpstreamJson1JsonbDynamicLarge20260531Test.php`, a focused
real-upstream JSON1/JSONB dynamic corpus sourced from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
  sections 1.10-6.50: `[#]` append slots, `[#-N]` reverse array indexes,
  left-to-right remove/mutation behavior, and malformed reverse path errors.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
  sections 1.1-2.1: legacy text-looking BLOB JSON compatibility for
  `json_valid`, arrows, inspection, mutation, and `json_tree`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
  sections 1.1-1.5: `json(json_pretty(input, indent))` canonical identity
  across null, empty, tab, and comment-like indent strings.

Focused movement:

- 3,066 distinct TestRunner PASS cases.
- 15,325 focused behavior assertions.
- Non-overlap: this batch uses a new 2026-05-31 large dynamic corpus over
  deterministic application documents and does not edit production source,
  dashboard files, or existing JSON corpus files.
- Expected dashboard classification: PASS-line growth only; mapped denominator
  remains complete at `1589 / 1589`.

Verification:

- Initial focused run exposed a test expectation mismatch: compound JSON from
  `SQLiteSelectExpression` is preserved as `SQLiteJsonSubtypeValue`, while the
  direct JSON function returns text. The test was corrected to compare the
  subtype JSON payload without changing production behavior.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicLarge20260531Test.php`
  passed: `1 test files, 15325 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicLarge20260531Test.php`
  passed: no syntax errors.

Dependency closure: no new support component is needed. The batch reuses
existing native PHP JSON parser/canonicalizer, JSONB encoder/decoder,
mutation, extraction, inspection, pretty-printing, tree, and select-expression
components.

Next task: continue with non-overlapping real upstream JSON behavior, preferably
remaining JSONB malformed/planner edges or JSON aggregate/table execution
guards that have not already been accepted.
