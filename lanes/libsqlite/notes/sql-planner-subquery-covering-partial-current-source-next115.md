# sql-planner-subquery-covering-partial-current-source-next115

This slice adds a bounded native PHP planner for a SQLite current-source
subquery/partial-index edge:

- selects the current schema/stat4 source when a prepared partial expression
  index has a changed schema cookie, stat4 generation, root page, or signature;
- probes the current partial index from deduplicated `IN (SELECT ...)` keys;
- treats the subquery projection as the covering payload when it supplies all
  requested result columns, so no deferred table lookup is required even though
  the expression index itself only covers the probe key;
- blocks the partial fast path when the subquery emits SQL NULL, projects an
  outside value that cannot imply the partial predicate, or omits a requested
  covering payload column.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSubqueryCoveringPartialCurrentSourceNext115Test.php
php lanes/libsqlite/examples/wordpress-subquery-covering-partial-current-source-next115.php
php -l lanes/libsqlite/src/SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerSubqueryCoveringPartialCurrentSourceNext115Test.php
php -l lanes/libsqlite/examples/wordpress-subquery-covering-partial-current-source-next115.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The patch composes the
existing native PHP expression-index planner with lane-local subquery projection
materialization.

Non-overlap: this avoids accepted next106 IN-subquery partial-index cursor
coverage by adding the distinct subquery-projection covering payload behavior
for current-source partial indexes. It also avoids accepted expression ORDER BY,
STAT4 range-cost, partial-index order, JSON table, VFS/WAL, and B-tree clusters.
