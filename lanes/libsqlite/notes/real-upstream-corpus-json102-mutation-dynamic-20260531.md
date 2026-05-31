# Real upstream corpus: json102 mutation dynamic

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T063558Z-0`

Accepted base: `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Upstream sections: `json102-330..400` mutation value semantics and
  `json102-410..430` object constructor value semantics.

Patch summary:

- Added `SQLiteRealUpstreamJson102MutationDynamicCorpusTest.php`.
- Adds 532 focused assertions over `json_insert`, `jsonb_insert`,
  `json_replace`, `jsonb_replace`, `json_set`, `jsonb_set`, `json_object`,
  and `jsonb_object`.
- Covers text input, JSONB input, `json_*` output, `jsonb_*` output, raw text
  values that must remain strings, JSON subtype values that must embed decoded
  JSON, and JSONB values that must embed decoded JSON.

Non-overlap:

- Avoids prior json102 `100..320` no-edit/extract batches.
- Avoids json105 reverse-index mutation, jsonb01 remove, JSON table planner,
  aggregate/window, operator, and malformed JSONB batches.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102MutationDynamicCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson102MutationDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MutationDynamicCorpusTest.php`
  - `1 test files, 532 assertions, 0 failures`

Dependency closure:

- No new support component needed. The slice reuses existing
  `SQLiteJsonMutation`, `SQLiteJsonConstructor`, `SQLiteJsonB`,
  `SQLiteBlobValue`, and `SQLiteJsonSubtypeValue` behavior.
