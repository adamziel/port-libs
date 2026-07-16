# yield-sqlite-trigger-returning-recursive-fk-current-next55

## Status delta

- Added `SQLiteTriggerForeignKeyReturningPlan` support for `ON UPDATE SET DEFAULT` and `ON DELETE SET DEFAULT` child-key rewrites.
- Added 55 focused PASS cases in `SQLiteTriggerReturningRecursiveFkCurrentNext55Test.php`.
- Added Application smoke coverage in `examples/application-trigger-returning-set-default-fk.php` for copied `wp_posts` / `wp_postmeta` delete-returning behavior.
- Updated `lane-status.json` `phpPass` from `20008` to `20063`, exactly matching the new focused PASS-line delta verified locally.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveFkCurrentNext55Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

## Non-overlap

This slice avoids accepted UPSERT trigger/FK yield behavior, recursive trigger savepoint RETURNING, deferred FK queue planning, VFS rollback/savepoint application, WAL byte truncation, grouped SELECT SQL text, JSON table cursor/source/constraint work, and B-tree page-move/freelist clusters. The new behavior is specifically FK `SET DEFAULT` action application inside the trigger/FK RETURNING helper.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded trigger/FK RETURNING planner and extends its native PHP FK-action dispatch.

## Next task

Continue with non-overlapping SQL executor/planner, JSON planner/JSONB, WAL/pager application, B-tree pointer-map/freelist, encoding/collation, durable VFS, or distinct suite-blocker work.
