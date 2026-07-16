# SQL Planner Partial Range Covering Current Source Next136

This slice adds `SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan`, a
bounded planner/materialization wrapper for covering partial range scans. It
reuses the accepted next131 partial range current-source planner, then
rechecks full predicate terms against the selected current source before
emitting the covering stream.

The covered edge is a copied `wp_options` plugin range where non-plugin rows
fall inside the raw `option_name` range. SQLite's partial-index scan cannot
leak those rows because the index only contains entries satisfying the partial
predicate. The next136 wrapper filters such rows before producing
current/next covering row pairs.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialRangeCoveringCurrentSourceNext136Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-planner-partial-range-covering-current-source-next136.php --self-test
application-planner-partial-range-covering-current-source-next136 self-test passed
```

Expected dashboard movement: `phpPass` +60 from the 60 independent PASS lines
in `SQLitePlannerPartialRangeCoveringCurrentSourceNext136Test.php`. Mapped
coverage remains conservative at `606 / 1589`; this is focused PHP planner
behavior over existing partial-index/range inventory, not a newly mapped
upstream manifest row.

Non-overlap: avoids accepted next131 ordinary covering partial range
materialization, next124 STAT4 partial range reprepare, next133 STAT4 partial
expression planning, expression ORDER BY, range-cost ranking, skip-scan
clusters, and JSON/VFS/WAL/B-tree accepted surfaces. The new behavior is
predicate-exact filtering of covering current-source rows for partial-index
terms that are not part of the index key prefix.

Dependency closure: no new support component is needed. The slice composes
existing native PHP CREATE INDEX parsing, multicolumn range planning, and
current-source covering materialization.

Root harness: not run - isolated micro-slice.
