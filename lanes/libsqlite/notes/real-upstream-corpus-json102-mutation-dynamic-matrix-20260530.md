real-upstream-corpus-json102-mutation-dynamic-matrix-20260530

Slice: real-upstream-corpus-json1-jsonb-dynamic-20260530T235106Z-0

Upstream source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test
- Ported behavior cluster: json102.test sections 320-400 covering json_insert(),
  json_replace(), json_set(), jsonb_insert(), jsonb_replace(), and jsonb_set()
  across existing-member, missing-member, SQL text value, JSON subtype value,
  JSONB value, text input, and JSONB input semantics.

Patch content:
- Added SQLiteRealUpstreamJson102MutationDynamicMatrixTest.php with a dynamic
  matrix over generic JSON documents, paths, mutation functions, replacement
  value classes, text input, JSONB input, and JSONB-returning function parity.
- The expectation oracle is lane-local and independent of SQLiteJsonMutation;
  it applies the json102 mutation rules to decoded PHP values and compares the
  port helper output after canonical JSON/JSONB decoding.

Focused evidence:
- php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102MutationDynamicMatrixTest.php
  -> No syntax errors detected.
- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MutationDynamicMatrixTest.php
  -> 1 test files, 9829 assertions, 0 failures.

Countability:
- Adds 6553 distinct TestRunner PASS cases in one focused file.
- Adds 9829 focused behavior assertions.
- Expected dashboard movement: PASS-line growth only after integration; mapped
  denominator remains unchanged because json102.test is already mapped.

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP JSON,
  JSONB, subtype, canonicalization, and mutation helpers.

Non-overlap:
- Avoids json105/jsonb01 reverse-index/remove duplication, JSON table cursor/
  source/constraint work, JSON104 merge patch matrices, and JSON aggregate/
  window behavior. This file focuses only on json102 mutation parity for real
  upstream sections 320-400.
