# real-upstream-corpus-json101-value-subtype-dynamic-20260531T013935Z

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T013935Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported upstream sections: `json101-5.10` and `json101-5.11`.

Behavior ported:

- `json_tree()` and `json_each()` container rows now expose the `value` column
  as a JSON subtype value, so feeding that value into `json_insert()` inserts
  the array/object as JSON instead of a quoted SQL string.
- Scalar string rows that look like JSON, matching upstream `json101-5.11`,
  remain ordinary SQL text and are quoted when inserted.
- Coverage exercises text JSON and JSONB inputs over 650 deterministic
  documents with nested objects, arrays, empty containers, and scalar string
  values that look like arrays.

Focused movement:

- New file: `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeDynamicTest.php`
- New focused PASS lines: 1301
- New focused assertions: 41604

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonTree.php && php -l lanes/libsqlite/src/SQLiteJsonEach.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeDynamicTest.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTree.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonEach.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeDynamicTest.php
1 test files, 41604 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicBulkTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicMegaTest.php
2 test files, 752794 assertions, 0 failures
```

Non-overlap:

- This slice does not add metadata-only rows or generated fake upstream script
  ids.
- It avoids accepted JSON101 constructor/mutation/path rows, JSON102 operator
  and mutation matrices, JSON103 aggregate/window rows, JSON104 patch rows,
  JSON105 reverse-index rows, JSON106/JSON108 invariant rows, JSON107 BLOB
  compatibility, JSON109 array insert, JSON501/JSON502 lexical/escaped path
  rows, JSONB01 remove rows, and JSON table cursor/source/hidden/visible
  constraint slices.
- Existing `json101` tree invariant tests cover path/fullkey/json/atom
  invariants; this slice owns the distinct upstream value-column JSON subtype
  propagation behavior from `json101-5.10` and scalar text preservation from
  `json101-5.11`.

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSONB,
  JSON subtype, JSON table-valued function, and JSON mutation helpers.
