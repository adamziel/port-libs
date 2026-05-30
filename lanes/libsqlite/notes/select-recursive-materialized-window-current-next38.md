# yield-sqlite-select-recursive-materialized-window-current-next38

Status delta: added bounded frame-aware `first_value()`, `last_value()`, and
`nth_value()` execution for parser-level SELECT SQL window expressions. The
focused coverage exercises `WITH RECURSIVE ... AS MATERIALIZED` import-window
rows with `ROWS`, `RANGE`, and `GROUPS` frames that start at `CURRENT ROW` and
look at following rows, matching the SQLite oracle shape for current/next
Application import previews.

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-recursive-materialized-window-current-next38.php --self-test
```

Focused test evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveMaterializedWindowCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Expected dashboard movement: `phpPass +45`, from `13431` to `13476`, based on
the 45 independent PASS lines in the focused test file. Mapped upstream
denominator is unchanged.

Non-overlap: this avoids accepted recursive CTE queue LIMIT/OFFSET behavior,
aggregate window frame helpers, SELECT SQL expression ORDER BY, grouped SELECT
SQL text, JSON table source/cursor/constraint clusters, B-tree freeblock/
overflow/page-move clusters, WAL/VFS writer and rollback clusters, Unicode
GLOB, and suite evidence handoffs. The new surface is value-window frame
execution over recursive materialized SELECT results.

Dependency closure: no new support component is needed; this reuses the
existing native `SQLiteSelectSql`, `SQLiteSelectQuery`, and
`SQLiteWindowFunction` execution path.
