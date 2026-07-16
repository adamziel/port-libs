# SQLite compound window EXCEPT LIMIT current-source next141

## Behavior

Adds `SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan`, a focused
current-source diagnostic for parser-level compound SELECTs that combine:

- a window-function output column in compound arms,
- an `EXCEPT` set operator over the complete output row, including window
  values,
- final compound `ORDER BY`,
- final `LIMIT` / `OFFSET`, and
- current-source vs next-source row-boundary and affinity-class deltas.

The Application smoke models copied `wp_options` rows being compared against a
network/options source before a bounded dashboard/import preview is sliced by
the final compound limit. It records both the pre-limit rowset and the visible
limited boundary so the clean integrator can distinguish set-removal changes
from final LIMIT/OFFSET truncation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptLimitCurrentSourceNext141Test.php`
  - `1 test files, 189 assertions, 0 failures`
  - `60` focused PASS lines

## Non-overlap

This does not repeat accepted compound SELECT row composition, compound
EXCEPT/ORDER affinity next138, compound LIMIT/window affinity next137, grouped
SELECT text, expression ORDER BY, JSON table SELECT sources, VFS/WAL/B-tree
storage slices, or accepted window aggregate/JSON aggregate work. The new
surface is the combined `EXCEPT` + window output + final compound limit
boundary diagnostic.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectCompound`,
`SQLiteSelectResult`, and `SQLiteWindowFunction` behavior.
