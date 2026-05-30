# yield-trigger-recursive-view-returning-current-source-next190

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows at the current-source to next-source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Plan`,
extending the accepted next187 drain-ticket handoff with a current-source
resume token and source signature gate. The gate binds the next-source
`RETURNING` stream to the last visible current-source resume token plus the
prepared view/trigger/RETURNING signature, so stale resumed cursors, mismatched
next-source resume rows, or changed current-source signatures keep attempted
next-source rows quarantined after the drain ticket itself has passed.

Application path: copied `wp_options` imports through a recursive view trigger can
drain current-source `RETURNING` rows, then prove the next source resumes from
the exact current cursor boundary before plugin migration rows become visible.

Focused verification:

```sh
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Plan.php

$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Test.php

$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next190.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next190.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext190Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures

$ php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next190.php
application-trigger-recursive-view-returning-current-source-next190 self-test passed
```

Expected dashboard movement: `phpPass +77` from the new focused test file
(`90822 -> 90899`). `benchmarkDenominator.mapped` is unchanged at
`617 / 1589`; this is current-source PHP behavior over already mapped trigger,
recursive view, and `RETURNING` inventory, not a newly hydrated upstream row.

Non-overlap: this avoids accepted next184 checkpoint admission, next186
post-reset rebinding, next187 drain-ticket matching, current accepted trigger
recursive/view RETURNING next187 batch174 behavior, row-value RETURNING,
deferred FK trigger, schema reparse, WAL/pager/VFS, B-tree, JSON, PRAGMA,
encoding, and suite evidence clusters. The new surface is specifically
resume-source token and prepared-source signature validation after the
current-source drain ticket has already admitted the base handoff.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view trigger `RETURNING` checkpoint/drain-ticket planners
and adds a bounded current-source resume validation layer.

Next task: wire this resume-source admission gate into the parser-level trigger
executor when native view-trigger bytecode owns `RETURNING` cursor suspension
and reprepare directly.
