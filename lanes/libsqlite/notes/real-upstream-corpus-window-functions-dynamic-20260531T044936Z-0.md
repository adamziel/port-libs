# Real Upstream Corpus: windowC varying separators

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260531T044936Z-0`

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

This slice ports real upstream SQLite `windowC.test` behavior for
`group_concat(value, separator) OVER (...)` with per-row separators. It covers
the upstream `windowC.test` section 1.* moving `ROWS` frames and the
`windowC.test` 2.0-2.1 UTF16/BLOB separator fuzz regression shape, without
adding generated fake upstream script ids or metadata-only PASS rows.

Added focused PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorFrameDynamic20260531Test.php`
- 1,074 distinct TestRunner PASS cases
- 21,826 focused behavior assertions
- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test`
  sections `1.*` and `2.0-2.1`

Non-overlap:

This avoids the already accepted `windowE` RANGE/ROWS dynamic batches,
ranking/navigation batches, JSON window aggregate batches, window pushdown
batches, and root-gate window GROUPS/RANGE no-ORDER guard. The behavior here
is specifically per-row separator selection in `group_concat()` window frames,
including empty, NULL, variable-length, and BLOB separators.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorFrameDynamic20260531Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorFrameDynamic20260531Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorFrameDynamic20260531Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 21826 assertions, 0 failures
```

Dependency closure:

No new support component is needed. This reuses lane-local
`SQLiteWindowFunction::groupConcatFrameBetweenSeparators()` and
`SQLiteBlobValue` text coercion.

