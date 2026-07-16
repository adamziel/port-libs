# JSON Aggregate Window Filter Regression next11

2026-05-27 isolated slice `yield-sqlite-json-window-aggregate-regression-next11`.

Behavior:

- Fixes `SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows()` and `jsonGroupObjectWindowFrameRows()` so aggregate `FILTER` is applied to frame contributors, not to source-row production.
- Preserves one window aggregate output row per ordered input row, including rows whose current aggregate argument is filtered out.
- Keeps existing EXCLUDE CURRENT ROW/GROUP/TIES behavior, JSON subtype payloads, JSONB dispatch, duplicate object-label text output, and empty aggregate frames.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowRegressionNext11Test.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowEdgeCorpusTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 85 assertions, 0 failures
```

New focused PASS-line delta:

- `SQLiteJsonAggregateWindowRegressionNext11Test.php`: 50 new PASS cases.
- Existing `SQLiteJsonAggregateWindowEdgeCorpusTest.php`: expectations updated for corrected upstream-style FILTER/window result shape; no `phpPass` credit claimed for those existing cases.

Application smoke:

```text
php lanes/libsqlite/examples/application-json-aggregate-window-filter-regression.php --self-test
application-json-aggregate-window-filter-regression self-test passed
```

Non-overlap:

- Avoids accepted JSON object aggregate/window DISTINCT/ORDER basics, JSON table window ranking, JSON table cursor/source/hidden/visible constraint work, JSON host joins, SELECT SQL GROUP BY/HAVING, and VFS/WAL/B-tree accepted clusters.
- This slice is limited to JSON aggregate window `FILTER` result-row preservation and frame contributor semantics.

Dependency closure:

- No new support component is needed; the patch reuses existing JSON constructor, JSON subtype, JSONB, and window-frame helper code.
