# real-upstream-corpus-json1-jsonb-dynamic-20260530T183237Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`

Added real upstream JSON aggregate coverage to
`SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`:

- `json103-100`, `json103-102`: empty filtered `json_group_array()` and
  `jsonb_group_array()` results.
- `json103-110`, `json103-111`, `json103-120`: row-order, array length, and
  grouped `json_group_array()` behavior, with JSONB parity assertions.
- `json103-200`, `json103-202`, `json103-210`, `json103-220`: empty-object,
  ordered object, and grouped `json_group_object()` behavior, with JSONB
  parity assertions.
- `json103-300`: aggregate subtype reset and nested JSON object aggregate
  input behavior.
- `json103-400`, `json103-410`: `ROWS 2 PRECEDING` window behavior for JSON
  array and object aggregates, with JSONB parity assertions.

Focused delta:

- Added 29 distinct real upstream TestRunner PASS cases.
- Final focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
- Result: `1 test files, 1073 assertions, 0 failures`.

Non-overlap:

- This slice ports `json103.test` aggregate and window behavior only.
- It does not repeat the existing real-corpus coverage for `json101.test`,
  `json102.test`, `json104.test`, `json105.test`, `json107.test`,
  `json108.test`, `json109.test`, `json501.test`, `json502.test`, or
  `jsonb01.test`.
- It adds no generated fake suite rows, metadata-only admissions, or
  domain-specific libsqlite API names.

Dependency closure:

- No new support component is needed. The cases reuse the existing native
  `SQLiteJsonAggregate`, `SQLiteJsonInspection`, `SQLiteJsonConstructor`,
  `SQLiteJsonB`, and `SQLiteBlobValue` implementations.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
- `git diff --check -- lanes/libsqlite`
- No no-domain guard file is present in this worktree; attempting
  `SQLiteNoWordPressSpecificApiTest.php` reported that the focused path does
  not exist.
