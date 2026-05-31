# real-upstream-corpus-json1-jsonb-dynamic-20260531T051717Z-0

Status: ready for integration review.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- Ported section: `jsonb01-2.0`, malformed JSONB blob rejection for JSON operators.

Behavior added:

- Added `SQLiteRealUpstreamJsonb01MalformedDynamicCorpusTest.php`.
- The test keeps the exact upstream corrupt JSONB blob `x'8ce6ffffffff171333'` and expands the same behavior over deterministic malformed JSONB header/payload boundaries derived from valid JSONB encodings.
- Malformed payloads reject `json()`, `json_extract()`, `json_type()`, `json_remove()`, `->`, and `->>` while preserving stable `json_valid(...,8)` rejection, positive `json_error_position()`, and immutable input bytes.
- Valid JSONB controls prove the same document family remains readable through canonicalization, extraction, type/array inspection, arrow operators, and `jsonb_remove()`.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON102 operator RHS parity, JSON107 text-BLOB legacy behavior, JSON105/109 mutation path batches, JSON aggregate/window batches, or the existing `jsonb01` remove-path corpus.
- The bounded surface is malformed JSONB rejection and valid-control parity for the upstream `jsonb01-2.0` operator failure path.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01MalformedDynamicCorpusTest.php` passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01MalformedDynamicCorpusTest.php` passed: `1 test files, 13561 assertions, 0 failures`, with `970` focused PASS lines.
- The generic API guard was not runnable because this worktree does not contain that guard test file.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. This reuses existing native JSONB, JSON validity/error-position, canonicalization, extraction, inspection, mutation, and SELECT-expression operator helpers.
