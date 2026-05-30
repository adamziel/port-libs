# JSON table generated path rowid xFilter current-source next176

Slice: `json-table-generated-path-rowid-cost-current-source-next176`

Behavior:

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext176()`.
- Carries accepted generated-path/rowid best-index state into an xFilter profile with argv tape, omit columns, seek program, pinned current-source rowids, output blocking, filter cost, and xFilter fingerprint.
- Replans when xFilter admission, argv tape, rowset, cost, or fingerprint changes between current and next copied Application JSON sources.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext176Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next176.php
```

Dashboard expectation:

- Expected focused `phpPass` delta: `+61` over accepted batch161 lane status (`81770 -> 81831`) after clean integration.
- No mapped upstream denominator change claimed.

Non-overlap:

- Avoids accepted next161/next173 generated-path/rowid admission and best-index-only behavior by modeling the xFilter argv/seek/output stage.
- Does not repeat accepted JSON table SELECT sources, cursor behavior, hidden constraints, visible constraints, host joins, LIMIT/OFFSET, window ranking, or malformed planner slices.

Dependency closure:

- No new support component needed; reuses native JSON table planning, JSON path validation, JSON tree row production, and current-source fingerprinting already present in `lanes/libsqlite/src`.
