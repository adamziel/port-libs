# JSON table generated path rowid seek cost current-source next159

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostNext159()` for the current-source planner boundary where a generated JSON path is already selected and rowid aliases can be costed as an xBestIndex-style seek set.

The new layer composes the accepted generated-path rowid cost planner (`next145`) and records seekable `=`, `IN`, and bounded `BETWEEN` rowid constraints, deduplicated rowid seek sets, matched/missing rowids, hit tapes, effective seek cost, and current/next replan reasons.

Application path: `examples/application-json-table-generated-path-rowid-seek-cost-current-source-next159.php` covers copied `wp_options` plugin rule JSON where generated path scans are filtered by `_rowid_ IN (...)` while the next option source changes.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidSeekCostCurrentSourceNext159Test.php
```

Result:

```text
1 test files, 51 assertions, 0 failures
```

Non-overlap: this avoids accepted parser-level JSON table SELECT source/cursor wiring, hidden/visible constraints, generated path rowid cost next145 as a standalone layer, path generated ORDER next137, hidden generated/path cost slices, and batch149 JSON table surfaces. This patch only adds rowid seek-set cost admission on top of the existing generated-path rowid-cost plan.

Dependency closure: no new support component is needed; this reuses native JSON table generated-path planning, rowid residual comparison, and current/next source transition metadata.
