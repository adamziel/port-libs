# compound-recursive-affinity-window-current-source-next129

Status: focused PHP behavior growth for parser-level compound SELECT output
over recursive CTE rows, SQLite affinity duplicate classes, and window FILTER
frames across current/next Application option sources.

Behavior:

- Adds `SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan`.
- Compares current and next compound SELECT rowsets with two windowed arms.
- Records recursive CTE trace rows and skipped numeric duplicate rows.
- Reports affinity value classes that change between current and next sources.
- Exposes compound arm/operator/order metadata and window FILTER/frame metadata.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNext129Test.php`
  - `1 test files, 112 assertions, 0 failures`
  - `36` PASS lines.
- `php lanes/libsqlite/examples/application-compound-recursive-affinity-window-current-source-next129.php --self-test`
  - `application-compound-recursive-affinity-window-current-source-next129 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNext129Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNext129Test.php`
- `php -l lanes/libsqlite/examples/application-compound-recursive-affinity-window-current-source-next129.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-compound-recursive-affinity-window-current-source-next129.php`

Dashboard delta:

- `phpPass`: `53324 -> 53360` from the verified 36 focused PASS lines.
- `mapped`: unchanged; this slice does not claim a new manifest-backed upstream
  inventory row.

Dependency closure:

- No new support component is needed. The slice reuses lane-local
  `SQLiteSelectSql`, recursive CTE tracing, compound SELECT execution, window
  expression parsing/execution, and SQLite affinity comparison helpers.

Non-overlap:

- Avoids accepted compound VALUES affinity/order next127, recursive affinity
  next120, correlated window FILTER next126, parser-level JSON source/cursor
  work, grouped SELECT text, expression ORDER BY, accepted WAL/VFS/B-tree
  storage clusters, and release-runner evidence work. The new surface is their
  composition point: recursive compound rows that preserve SQLite affinity
  classes while feeding window FILTER output across a current-source to
  next-source transition.

Next task:

- Continue only with a non-overlapping SQL executor/planner gap, preferably one
  that adds focused tests for current-source parser behavior not already covered
  by compound, recursive, affinity, or window accepted clusters.
