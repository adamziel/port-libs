# real-upstream-corpus-json501-json502-dynamic-20260530

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T211558Z-0`

Accepted base: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`

Ported behavior:

- `json501.test` sections 1, 2, 3, 4, 6, 7, 8, 10, 11, and 12: JSON5 identifier keys, trailing object/array commas, single-quoted strings, `\xNN` escapes, hexadecimal numbers, leading/trailing decimal points, line/block comments, and JSON5 validity flags.
- `json502.test` sections 1, 3, and 5: JSON5 input through `json_tree`, escaped object labels, escaped path labels, JSON patch label comparison, and quoted quote-key mutation/extraction.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501Json502DynamicBulkTest.php`
- Result: `1 test files, 7388 assertions, 0 failures`
- PASS lines counted from focused output: `1443`

Non-overlap:

- Avoids the accepted JSON101/102/104/105/107/109/jsonb01 dynamic files, JSON table source/cursor/hidden/visible constraint slices, JSON host joins, JSON path array-insert bulk, and JSONB remove/patch dynamic coverage.
- This file specifically expands JSON5 canonicalization/validity/extraction/tree/path behavior from `json501.test` and `json502.test`.

Dependency closure:

- Reuses existing native PHP JSON helpers: `SQLiteJsonCanonical`, `SQLiteJsonValidity`, `SQLiteJsonExtract`, `SQLiteJsonMutation`, `SQLiteJsonPatch`, `SQLiteJsonTree`, `SQLiteJsonB`, and `SQLiteSelectExpression`.
- No new support component is required.
