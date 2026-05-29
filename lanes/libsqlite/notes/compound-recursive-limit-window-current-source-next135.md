# compound-select-recursive-limit-window-current-source-next135

Status: focused current-source behavior growth for parser-level compound SELECT
over recursive CTE rows where the recursive queue has its own `ORDER BY` /
`LIMIT`, each compound arm can compute `CURRENT ROW ... FOLLOWING` window
values, and the final compound rowset applies `ORDER BY` / `LIMIT` / `OFFSET`
after current/next WordPress option sources are combined.

Behavior:

- Adds `SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan`.
- Compares current and next compound rowsets for copied `wp_options` dependency
  walks.
- Records recursive queue trace rows and the terminal queue-limit boundary.
- Records compound final limit metadata, window frame metadata, changed row
  signatures, and current-source replan reasons.
- Adds a WordPress smoke for import previews where new option dependencies
  change the recursive queue and the final limited compound window rowset.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitWindowCurrentSourceNext135Test.php`
  - `1 test files, 164 assertions, 0 failures`
  - `53` PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-recursive-limit-window-current-source-next135.php --self-test`
  - `wordpress-compound-recursive-limit-window-current-source-next135 self-test passed`

Dashboard delta:

- `phpPass`: `56681 -> 56734` from the verified 53 focused PASS lines.
- `mapped`: unchanged; this slice does not claim a new manifest-backed upstream
  inventory row.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  `SQLiteSelectSql`, recursive CTE queue tracing, compound SELECT ordering and
  tail limit execution, window frame evaluation, and WordPress row-array
  fixtures.

Non-overlap:

- Avoids accepted compound recursive collation LIMIT next132, compound window
  frame LIMIT next131, compound recursive affinity/window next129, compound
  recursive LIMIT current-source next117, SELECT SQL grouped/subquery/ORDER
  accepted clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree
  storage clusters, encoding clusters, trigger work, and suite-runner evidence.
  The narrower surface is their uncovered composition: recursive queue LIMIT
  state feeding compound arms that evaluate `CURRENT ROW ... FOLLOWING` windows
  before a final current/next compound LIMIT boundary.

Next task:

- Continue with a non-overlapping SQL executor/planner current-source gap,
  preferably one outside recursive compound/window/LIMIT behavior.
