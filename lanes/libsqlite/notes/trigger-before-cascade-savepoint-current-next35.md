# Trigger before cascade savepoint current-next35

Slice: `yield-sqlite-trigger-before-cascade-savepoint-current-next35`

This slice adds bounded native PHP planning for parent `BEFORE DELETE` trigger
effects before `ON DELETE CASCADE` child/grandchild actions begin, with
savepoint current/attempted row images for `RAISE(ROLLBACK)` and `RAISE(IGNORE)`
paths. The covered behavior is parent-trigger yield at the current row before
the FK cascade scans children: audit rows see pre-cascade child/detail counts,
BEFORE triggers can rehome or delete child rows before the cascade, and a
rollback-trigger guard restores current parent/child/grandchild rows to the
savepoint image.

Files:

- `src/SQLiteTriggerBeforeCascadeSavepointPlan.php`
- `tests/SQLiteTriggerBeforeCascadeSavepointCurrentNext35Test.php`
- `examples/application-trigger-before-cascade-savepoint-current-next35.php`

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerBeforeCascadeSavepointCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures

$ php lanes/libsqlite/examples/application-trigger-before-cascade-savepoint-current-next35.php --self-test
application-trigger-before-cascade-savepoint-current-next35 self-test passed

$ php -l lanes/libsqlite/src/SQLiteTriggerBeforeCascadeSavepointPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerBeforeCascadeSavepointPlan.php

$ php -l lanes/libsqlite/tests/SQLiteTriggerBeforeCascadeSavepointCurrentNext35Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerBeforeCascadeSavepointCurrentNext35Test.php

$ php -l lanes/libsqlite/examples/application-trigger-before-cascade-savepoint-current-next35.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-before-cascade-savepoint-current-next35.php
```

Status delta:

- `phpPass`: `12271 -> 12332` from 61 new focused PASS assertions.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP
  behavior evidence and does not claim a fresh upstream inventory unit.

Non-overlap:

- Avoids accepted child-trigger cascade effects, FK cascade update triggers,
  UPSERT trigger/FK yield, recursive trigger/savepoint, VFS savepoint rollback,
  WAL byte truncation, rollback-journal/VFS apply, B-tree page/freelist
  clusters, JSON table cursor/source/constraint work, SELECT SQL text/
  subquery/group/order clusters, Unicode GLOB, and batch29 FK cascade update
  trigger coverage.
- The new surface is specifically parent `BEFORE DELETE` trigger effects before
  FK cascade child scans plus savepoint current/attempted row preservation.

Dependency closure:

- No new support component is needed. The slice reuses lane-local PHP row-array
  trigger/FK/savepoint planning and does not require ext/sqlite, upstream
  binaries, live services, or provider credentials.
