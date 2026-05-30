# Attach Temp View Collation Current Next33

## Status

- Added `SQLiteAttachTempViewCollationPlan`, a bounded native PHP planner for collation metadata that flows through attached/temp view triggers.
- Covered temp schema shadowing, attached schema pinning, INSTEAD OF view targets, body INSERT/UPDATE/DELETE targets, and trigger-body SELECT expressions.
- Added a Application smoke for copied `wp_options` / `wp_option_audit` views and triggers without requiring `ext/sqlite`.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempViewCollationCurrentNext33Test.php`
- Result: `1 test files, 63 assertions, 0 failures`

## Non-overlap

This slice avoids accepted attach temp/VFS open planning, attach temp view/trigger yield operation materialization, PRAGMA collation metadata, Unicode GLOB ranges, expression ORDER BY, JSON table source/cursor behavior, VFS writer/sync/lock/rollback paths, and B-tree page-move/freeblock/overflow clusters. It only adds attach/temp/attached trigger collation propagation evidence around view targets and yielded body operations.

## Dependency closure

No new support component is needed. The slice reuses the lane-local schema catalog and schema records, and keeps the behavior bounded to native PHP SQL text inspection for current libsqlite closure.
