# SQLite planner covering STAT4 range current-source next138

Status: focused PHP behavior growth for non-partial covering STAT4 range row-stream admission.

This slice adds `SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan`. It composes the accepted covering range-order planner, then materializes the selected current source's covering row stream, STAT4 range buckets, row-stream signatures, and cursor recheck opcode for a normal multicolumn covering index over copied `wp_options` rows.

WordPress path: `wordpress-planner-covering-stat4-range-current-source-next138.php` models a plugin-option range scan on `(blog_id, autoload, option_name, option_value, rowid)` after a copied import updates schema/stat4 generations. The planner reparses to the current source, admits only rows matching the equality prefix and range bounds, keeps payload columns in the index, and avoids a table lookup.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringStat4RangeCurrentSourceNext138Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-planner-covering-stat4-range-current-source-next138.php --self-test`
  - `wordpress-planner-covering-stat4-range-current-source-next138 self-test passed`

PASS delta: `+59` focused PASS lines. `lane-status.json` `phpPass` moves from `59517` to `59576`. Mapped upstream coverage remains `606 / 1589`; this reuses existing covering-index, STAT4, range-planner, and current-source inventory rather than claiming a fresh manifest-backed row.

Non-overlap: avoids accepted next131 partial range, next135 STAT4 partial covering, next136 partial residual recheck, expression-index range cost, expression ORDER BY, skip-scan, JSON, VFS, WAL, and B-tree clusters. The new surface is non-partial covering STAT4 range row admission and bucket signatures.

Dependency closure: no new support component is needed. The slice reuses native PHP CREATE INDEX parsing, covering range-order planning, STAT4 sample diagnostics, and current-source fences.
