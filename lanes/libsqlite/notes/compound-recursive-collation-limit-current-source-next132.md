# compound-select-recursive-collation-limit-current-source-next132

Status: focused current-source behavior growth for parser-level compound SELECT
over recursive CTE rows with left-arm `COLLATE NOCASE` set membership and final
`ORDER BY` / `LIMIT` / `OFFSET` applied after current/next WordPress option
sources are combined.

Behavior:

- Adds `SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan`.
- Compares current and next compound rowsets for recursive option dependency
  names.
- Records left-arm compound set collation metadata separately from final
  `ORDER BY ... COLLATE` metadata.
- Records recursive CTE trace rows, skipped NOCASE duplicate rows, final limit
  suppression counts, changed current/next names, and replan reasons.
- Adds a WordPress smoke for copied `wp_options` import previews where a
  recursive option-name walk is compounded with current autoload=no rows before
  final LIMIT/OFFSET.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveCollationLimitCurrentSourceNext132Test.php`
  - `1 test files, 159 assertions, 0 failures`
  - `50` PASS lines.
- `php lanes/libsqlite/examples/wordpress-compound-recursive-collation-limit-current-source-next132.php --self-test`
  - `wordpress-compound-recursive-collation-limit-current-source-next132 self-test passed`

Dashboard delta:

- `phpPass`: `55029 -> 55079` from the verified 50 focused PASS lines.
- `mapped`: unchanged; this slice does not claim a new manifest-backed upstream
  inventory row.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  `SQLiteSelectSql`, recursive CTE tracing, compound SELECT collation metadata,
  final result ordering/limit execution, and WordPress row-array fixtures.

Non-overlap:

- Avoids accepted compound VALUES affinity/order next127, recursive affinity
  next120, compound recursive window next129, compound order/limit next110,
  compound collation set operator next12 as a standalone primitive, expression
  ORDER BY, grouped SELECT text, parser-level JSON table source/cursor/
  constraint work, VFS/WAL/B-tree storage clusters, and suite-runner evidence.
  The new surface is the composition point: recursive current-source rows and
  WordPress option rows are compared under the left compound arm's collation
  and then narrowed by the final compound LIMIT/OFFSET window.

Next task:

- Continue with a non-overlapping SQL executor/planner current-source gap,
  preferably one that adds parser-level behavior not already covered by
  compound, recursive, collation, or LIMIT accepted clusters.
