# real-upstream-corpus-json1-jsonb-dynamic-20260530T212029Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

Ported behavior cluster:

- `json501` JSON5 object keys, trailing object/array commas, single-quoted strings, escaped line continuations, hex escapes, signed/hex/Infinity/NaN number forms, comments, extended whitespace, and raw control-string handling.
- `json502` escaped label/path behavior, including quoted embedded quotes, `\xNN` label escapes, `\u00NN` control labels, `json_tree()` fullkey compatibility, malformed object-label boundaries, and JSONB parity.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson501502EscapedStressTest.php`.
- The test builds 320 deterministic real-upstream-inspired rows from the hydrated scripts and verifies canonical JSON5 text, JSONB strict validity, scalar path extraction from text and JSONB, `json_tree()` fullkeys, `json_set()`, `json_insert()`, `json_replace()`, `json_patch()`, `jsonb_patch()`, decoded structure parity, and malformed boundary rejection.
- Focused assertion count: `16654`.
- Focused PASS lines: `6`.

Non-overlap:

- This is not metadata admission and does not generate fake upstream script IDs.
- It does not repeat JSON table cursor/source/hidden/visible constraint work, JSON107 legacy BLOB text, JSON109 array insert, JSON105 reverse-index mutation, or earlier smaller JSON501/JSON502 row coverage. The new value is the larger escaped-key/path JSON5 stress matrix plus JSONB and mutation parity over generated upstream-feature combinations.
- No WordPress-specific libsqlite API or scenario was added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson501502EscapedStressTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501502EscapedStressTest.php`
  - `1 test files, 16654 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSON5, JSON canonicalization, JSONB, JSON extraction, JSON tree, mutation, and patch helpers.
