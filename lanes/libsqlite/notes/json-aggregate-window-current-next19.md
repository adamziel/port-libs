# JSON Aggregate Window Current Next19

2026-05-27 isolated slice `yield-sqlite-json-aggregate-window-current-next19`.

Behavior:

- Adds stateful `SQLiteJsonAggregateState` collection/finalization for JSON aggregate window frame rows.
- Covers `CURRENT ROW` to following-row frames with ORDER BY peer keys, FILTER truthiness, EXCLUDE modes, JSON subtype payloads, JSONB dispatch, duplicate object labels, and state summary counts.
- Reuses existing `SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction()` and `jsonGroupObjectWindowFrameRowsSqlFunction()` rather than adding a separate aggregate implementation.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowCurrentNext19Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 42 assertions, 0 failures
```

New focused PASS-line delta:

- `SQLiteJsonAggregateWindowCurrentNext19Test.php`: 31 new PASS cases.

Application smoke:

```text
php lanes/libsqlite/examples/application-json-aggregate-window-current-next.php --self-test
application-json-aggregate-window-current-next self-test passed
```

Non-overlap:

- Avoids accepted JSON object aggregate/window DISTINCT/ORDER/FILTER basics, JSON aggregate window edge next7, JSON aggregate window FILTER/result-row regression next11, JSON table cursor/source/hidden/visible constraints, JSON host joins, SELECT SQL GROUP BY/ORDER/subquery work, and VFS/WAL/B-tree accepted clusters.
- This slice is limited to the unhandled stateful aggregate step/finalize path for current-row-to-following JSON aggregate windows.

Dependency closure:

- No new support component is needed; the patch reuses existing JSON constructor, JSON subtype, JSONB, and window-frame helper code.
