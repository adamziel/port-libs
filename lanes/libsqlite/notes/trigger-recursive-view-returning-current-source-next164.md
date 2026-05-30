# Trigger recursive view RETURNING current-source next164

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows when trigger-body `RAISE(IGNORE)`-style skips and conflict
retry decisions occur at a current-source to next-source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Plan`.
It extends the accepted next161 recursive view-trigger source-drain surface by
tracking rows suppressed by trigger conditions before they can emit
`RETURNING`, recording conflict replacement versus conflict-ignore decisions,
and keeping next-source attempts diagnostic-only until the next source is
admitted.

Application path:
`application-trigger-recursive-view-returning-current-source-next164.php` models a
copied `wp_options` import view where a plugin import recursively creates retry
rows, skips disabled imports via trigger-body ignore behavior, replaces the
current `siteurl`, and plans a next-source view that would replace `home` and
skip a new disabled import only after reprepare.

Focused verification:

```text
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Plan.php

$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Test.php

$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next164.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next164.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this new test file (`+55`, from `73438` to `73493`). Mapped upstream coverage
remains `610 / 1589`; this is additional current-source PHP behavior over the
already mapped trigger/view/RETURNING surface, not a newly hydrated upstream
Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses
lane-local row-array view-trigger, recursive-trigger, RETURNING, and
current-source planning primitives.

Non-overlap: avoids accepted next161 recursive INSTEAD OF view-trigger
RETURNING source-drain behavior, next149 non-recursive UPSERT view-trigger
RETURNING source-drain behavior, next134/next146 view-trigger savepoint
rollback/admission behavior, row-value RETURNING savepoint retry clusters,
schema view/trigger reparse clusters, and VFS/WAL/B-tree/JSON/encoding/PRAGMA
surfaces. The new surface is specifically skipped RETURNING rows and
conflict-retry diagnostics while the current recursive view source remains
pinned and the next view source is attempted only.

Next task: wire this skip/conflict boundary into the parser-level trigger
executor once native view trigger bytecode owns recursive trigger stepping
directly.
