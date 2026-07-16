# real-upstream-corpus-json1-jsonb-dynamic-20260530T195837Z-0

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`.

Added `SQLiteRealUpstreamJson103WindowMatrixDynamicTest.php`, a high-yield
focused PHP corpus batch sourced from the hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`

Ported scenario family:

- `json103-400`: `json_group_array()` as a window function over the upstream
  seven-row `t4` value set.
- `json103-410`: `json_group_object()` as a window function over the same row
  set and rowid keys.

Coverage shape:

- Exhaustive `ROWS N PRECEDING M FOLLOWING` frame matrix for `N` and `M` from
  `0` through `6`, with text JSON and JSONB parity for array and object
  aggregate frames.
- `903` ordered dynamic frame cases over rotations of the upstream `t4` row
  set, verifying independent frame-oracle output, JSONB decode parity,
  first/last frame boundary extraction, object member extraction, and frame
  length/type stability.
- The batch adds `1,002` distinct TestRunner PASS cases and `13,315` focused
  behavior assertions.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103WindowMatrixDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 13315 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `496269 -> 497271` if admitted as a new focused test file.
- Mapped denominator: unchanged at `1472 / 1589`; this is behavior growth over
  already-mapped upstream `json103.test`.

Non-overlap:

This does not add production source, fake upstream script ids, metadata-only
admission records, or domain-shaped APIs. It avoids accepted JSON table
cursor/source/hidden/visible constraint work, JSON host joins, JSON102 scalar
and mutation rows, JSON104 merge-patch, JSON105 reverse paths, JSON106/108
invariants, JSON109 array insert, JSON501/502 JSON5 and escaped labels, and
JSONB remove coverage. The new focus is upstream `json103.test` aggregate
window frame behavior at substantially larger frame-matrix volume than the
existing narrow two-preceding checks.

Dependency closure:

No new support component is needed. The batch reuses existing native
`SQLiteJsonAggregate`, `SQLiteJsonB`, `SQLiteJsonCanonical`,
`SQLiteJsonExtract`, and `SQLiteJsonInspection` behavior.

Root harness: not run - isolated micro-slice.
