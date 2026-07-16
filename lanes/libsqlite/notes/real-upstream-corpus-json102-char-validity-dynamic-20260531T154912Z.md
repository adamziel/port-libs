# real-upstream-corpus-json102-char-validity-dynamic-20260531T154912Z

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T154912Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Ported sections: `json102-610` and `json102-620`.

Behavior ported:

- `json102-610`: `json_valid()` accepts JSON text assembled from SQL-style
  `char(123) || body || char(125)` object construction.
- `json102-620`: `json_valid()` rejects the same constructed object when the
  closing brace is omitted.
- The focused PHP corpus expands the upstream pair across 600 dynamic object
  bodies and validates direct JSON helpers plus `SQLiteSelectExpression`
  dispatch through `char()`, `||`, `json_valid()`, `json()`, and `jsonb()`.

Non-overlap:

- Does not repeat accepted JSON table cursor/source/hidden/visible constraint
  behavior, JSON101 null/substructure/surrogate/path coverage, JSON102 tree
  search/operator/lexical numeric/control coverage, JSON105 reverse-index
  mutation, JSON501/502 JSON5 escaped-label stress, JSON103 aggregate/window
  behavior, or JSONB malformed/remove coverage.
- This slice targets the previously uncovered `json102-610` and `json102-620`
  char-built validity boundary only.

Dependency closure:

- No new support component is needed. The slice reuses native PHP JSON
  validity/canonicalization, JSONB encoding, JSON path inspection, core scalar
  `char()`, and SELECT expression concatenation/function dispatch.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102CharObjectValidityDynamicTest.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102CharObjectValidityDynamicTest.php`
  passed: `1 test files, 13807 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
