# real-upstream-corpus-json1-jsonb-dynamic-20260531T054018Z-0

Added a real-upstream JSON102 indexed mutation batch backed by
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.

Ported upstream sections:

- `json102-1700`: table/index setup with expression index on `memo->>'y'`.
- `json102-1710`: `JSON_REMOVE(memo, '$.y')` removes the indexed JSON key and preserves the rest of the row image.
- `json102-1720`: `JSON_SET(memo, '$.y', value)` restores the key only after `JSON_TYPE(memo, '$.y')` is SQL NULL.

Implementation:

- `SQLiteJsonPathIndexedUpdatePlan` now applies `json_remove` and `jsonb_remove` mutations before recomputing indexed JSON path keys.
- `SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php` adds 1000 distinct text/JSONB dynamic rows. Each row verifies index delete/insert metadata, canonical row images, `json_type` missing/restored behavior, and `json_extract` preservation.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php`
  - `1 test files, 15004 assertions, 0 failures`
  - `1001` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathIndexedUpdateTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php`
  - `2 test files, 15066 assertions, 0 failures`
  - `1063` PASS lines
- `php -l lanes/libsqlite/src/SQLiteJsonPathIndexedUpdatePlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is absent in this worktree

Expected selected PASS movement: `+1001`, from `2323745` to `2324746`.
Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

This does not repeat JSON table cursor/source/hidden/visible constraint work,
JSON104 merge-patch, JSON105 reverse-index mutation, JSON102 operator/path
stress, JSONB malformed corpus, JSON501/502 escaped path/control coverage, or
JSON aggregate/window coverage. The new behavior is specifically expression
index maintenance after JSON remove/set mutation over the upstream
`memo->>'y'` scenario.

Dependency closure:

No new support component is needed. The slice reuses native JSONB, JSON
canonicalization, JSON remove/set mutation, JSON type/extract, and the existing
JSON path indexed update planner.
