# Trigger deferred FK current-next24

Slice: `yield-sqlite-trigger-fk-deferred-cluster-current-next24`

This slice adds `SQLiteTriggerDeferredForeignKeyPlan`, a bounded native PHP
planner for trigger body writes that queue deferred foreign-key checks until
statement/commit boundaries. It covers trigger INSERT/UPDATE child checks,
parent DELETE checks, missing-parent commit blocking, repair-before-commit
success, immediate `RESTRICT`, rollback preview, and malformed input guards.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredForeignKeyCurrentNext24Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-trigger-deferred-fk-current-next24.php --self-test
application-trigger-deferred-fk-current-next24 self-test passed
```

Syntax:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerDeferredForeignKeyPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredForeignKeyCurrentNext24Test.php
php -l lanes/libsqlite/examples/application-trigger-deferred-fk-current-next24.php
```

Status delta:

- `phpPass`: `8166 -> 8220` from 54 verified focused PASS lines.
- `benchmarkDenominator.mapped`: `458 -> 459` for one newly mapped focused
  trigger/deferred-FK evidence row.

Non-overlap:

- Avoids accepted attach/temp trigger FK schema resolution, recursive trigger
  savepoint planning, single/composite deferred cascade/action corpora,
  savepoint page-image/WAL rollback, rollback-journal/VFS apply, and batch21
  trigger/FK clusters.

Dependency closure:

- No new support component is needed. This reuses lane-local row-array
  planning and the existing PHP test harness; no external SQLite extension or
  upstream binary is required.

Next task:

- Continue with a non-overlapping SQL executor/planner, WAL/pager application,
  JSON planner, B-tree freelist, encoding/collation, or release-suite blocker
  slice.
