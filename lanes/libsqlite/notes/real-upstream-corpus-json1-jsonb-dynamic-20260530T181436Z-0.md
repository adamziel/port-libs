## real-upstream-corpus-json1-jsonb-dynamic-20260530T181436Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`

Ported behavior cluster:

- `json103-100`, `json103-102`: empty `json_group_array()` and `jsonb_group_array()` aggregate results.
- `json103-101`: aggregate rejection for raw BLOB values that JSON cannot hold.
- `json103-110`, `json103-111`: mixed scalar aggregate order and array length across integer, real, NULL, and text values.
- `json103-120`: grouped `json_group_array()` partitions over the upstream 100-row recursive table shape.
- `json103-200`, `json103-202`: empty `json_group_object()` and `jsonb_group_object()` aggregate results.
- `json103-201`: object aggregate rejection for raw BLOB values.
- `json103-210`, `json103-220`: object aggregate member order, grouped object partitions, and JSONB parity.
- `json103-300`: aggregate subtype reset behavior for plain scalars beside JSON object subtype inputs.
- `json103-400`, `json103-410`: JSON array/object aggregate window behavior over `ROWS 2 PRECEDING`.

Focused assertion count:

- `SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php`: 13 focused PASS cases / 864 assertions.

Non-overlap:

- This is a new `json103.test` aggregate corpus file. It does not repeat the prior `json105.test` dynamic path batch, `json102.test` extract/mutation batch, `json107.test` legacy BLOB batch, JSON table cursor/source/constraint batches, or metadata-only suite admission rows.

Dependency closure:

- No new support component is needed. The batch reuses native PHP `SQLiteJsonAggregate`, `SQLiteJsonB`, `SQLiteJsonCanonical`, `SQLiteJsonInspection`, `SQLiteJsonSubtypeValue`, and `SQLiteBlobValue`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php`
  - `1 test files, 864 assertions, 0 failures`
