# real-upstream-corpus-json103-aggregate-dynamic-20260531

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T004457Z-0`

Base accepted HEAD: `93b324b07783b617d6c0938ad7bcd94b70aaa32e`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`

Behavior covered:

- `json103-100`, `json103-102`, `json103-200`, and `json103-202`: empty
  `json_group_array`, `jsonb_group_array`, `json_group_object`, and
  `jsonb_group_object` aggregate results.
- `json103-101` and `json103-201`: BLOB input rejection for JSON aggregate
  arrays and objects.
- `json103-110` and `json103-210`: mixed real, integer, SQL NULL, and text
  aggregate order over the upstream `t1` row shape.
- `json103-120` and `json103-220`: grouped aggregate rows by `b` with stable
  row order.
- `json103-400` and `json103-410`: `ROWS 2 PRECEDING` window behavior for
  `json_group_array` and `json_group_object`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php`
- Result: `1 test files, 1969 assertions, 0 failures`.
- Focused PASS-line growth: 1969 distinct TestRunner PASS cases in one new
  behavior test file.

Non-overlap:

This batch does not repeat accepted JSON table cursor/source/hidden/visible
constraint work, JSON101/102 constructor/extract coverage, JSON105 dynamic path
mutation coverage, JSON106/108 invariant coverage, JSON501/502 JSON5 coverage,
or JSONB remove/path-operator coverage. It ports the remaining real upstream
`json103.test` JSON aggregate and aggregate-window behavior directly into PHP
assertions.

Dependency closure:

No new support component is needed. The batch reuses existing native PHP
`SQLiteJsonAggregate`, `SQLiteJsonAggregateState`, `SQLiteJsonB`,
`SQLiteBlobValue`, and JSON canonicalization helpers.
