# SQL planner STAT4 range order current-source next92

This slice adds `SQLiteMultiColumnRangePlan::stat4RangeOrderCurrentSourceNext92()` for bounded current/next diagnostics when a multicolumn STAT4 range scan also satisfies `ORDER BY` from the current index source.

Focused behavior:

- reports the selected ranked multicolumn range plan, current STAT4 source column/offset, range boundary current/next samples, matched current-source keys, and next alternative plan;
- distinguishes index-order consumption from `USE TEMP B-TREE FOR ORDER BY` when direction or usable source ordering changes;
- keeps covering, residual predicate, skip-scan, and dependency-closure metadata in the same bounded planner record;
- adds a copied `wp_options` smoke for plugin option-name range scans ordered by `option_name`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4RangeOrderCurrentSourceNext92Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

Non-overlap: this avoids accepted STAT4 expression range costs, partial-covering planner work, join-order planning, expression `ORDER BY` execution, parser-level SELECT SQL text, JSON table source/cursor/constraint work, B-tree overflow/page-move/root-collapse clusters, WAL checkpoint/savepoint/reader-pin work, VFS writer/sync/lock clusters, and Unicode GLOB behavior. The new surface is multicolumn STAT4 range-scan ORDER BY current-source evidence for the next planner batch.

Dependency closure: no new support component is needed; this composes existing native multicolumn planner, CREATE INDEX parsing, STAT4 sample normalization, and focused PHP TestRunner evidence only.
