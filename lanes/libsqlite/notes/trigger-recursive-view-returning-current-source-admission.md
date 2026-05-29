# Trigger Recursive View RETURNING Current Source Admission

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at a current-source to next-source boundary.

This slice consolidates the recursive view `RETURNING` admission entry point
into `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan` with stable
descriptive method and helper names.
It models copied WordPress `wp_options` rows inserted through an import view
whose `INSTEAD OF` trigger recursively inserts child option rows while
`PRAGMA recursive_triggers` is enabled. The current view/trigger source drains
its depth-first `RETURNING` rows first. A changed next view source is planned
and its attempted recursive `RETURNING` rows are retained for reprepare
diagnostics, but they are not exposed until the next source is admitted.

WordPress path:
`wordpress-trigger-recursive-view-returning-current-source-admission.php` covers a
plugin import view where a current trigger recursively inserts
`plugin_seed_child` rows while a pending plugin migration rewrites the next view
to include an `origin` column and a different recursive suffix.

Focused verification:

```text
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php

$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceAdmissionTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceAdmissionTest.php

$ php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-admission.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-admission.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceAdmissionTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures

$ php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-admission.php --self-test
wordpress-trigger-recursive-view-returning-current-source-admission self-test passed
```

Dashboard delta: no `phpPass` or mapped-coverage change; this is a
consolidation-only cleanup that preserves the same 74 focused assertions under
a stable direct test name.

Dependency closure: no new support component is needed. The slice reuses
lane-local row-array view-trigger, recursive-trigger, and RETURNING planning
primitives.

Non-overlap: avoids accepted next149 non-recursive UPSERT view-trigger
RETURNING source-drain behavior, next134 view-trigger savepoint rollback
admission, next128 recursive trigger RETURNING/deferred-FK view materialization,
row-value RETURNING savepoint clusters, schema view/trigger reparse clusters,
and VFS/WAL/B-tree/JSON/encoding/PRAGMA surfaces. The new behavior is
specifically recursive `INSTEAD OF` view-trigger `RETURNING` yield ordering
while current view source remains pinned and next view source is attempted only.

Next task: wire this current/next recursive view-trigger boundary into the
parser-level trigger executor once native view trigger bytecode owns recursive
trigger stepping directly.
