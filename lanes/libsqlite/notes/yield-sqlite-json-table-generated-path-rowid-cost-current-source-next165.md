# JSON table generated path rowid cost current-source next165

Slice: `json-table-generated-path-rowid-cost-current-source-next165`

Behavior added:

- `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext165()` composes the accepted generated-path rowid cost/source plan with rowid seek-cost state.
- The profile records xBestIndex-style generated path and rowid argv indexes, omitted/residual constraint columns, rowid alias normalization, order-by consumption, estimated rows/cost, and stable-key transitions for current/next source comparison.
- Covered rowid operators are `=`, `IN`, and bounded `BETWEEN`; unusable or non-seekable rowid constraints remain residual scans.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext165Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-next165.php
```

Non-overlap:

- Does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, generated path rowid cost next162, or selected current-source JSON table projection work.
- This slice is only the next165 rowid-seek cost/admission layer over the existing generated-path rowid current-source planner.

Dependency closure:

- No new support component is needed. The slice reuses the existing pure-PHP JSON table planner, JSON path inspection, and rowid constraint helpers.
