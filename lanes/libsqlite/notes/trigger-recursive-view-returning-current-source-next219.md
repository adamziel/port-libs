# trigger-recursive-view-returning-current-source-next219

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` current-source handoff after the accepted next217 provenance fence.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Plan`.
It models the boundary where current-source and admitted next-source
`RETURNING` rows are already visible, but the following current-source
view/trigger generation must wait until the next-source RETURNING cursor has
been reset with the expected reset token and cursor. Missing, unexpected, or
out-of-order reset receipts hold the following current source; cursor or
following-source token mismatches also hold it. The accepted next217 provenance
state remains the base admission gate.

Application path:
`application-trigger-recursive-view-returning-current-source-next219.php` models a
copied `wp_options` import through a recursive view trigger where admitted
next-source rows (`home`, `next_plugin`) must be reset before a following
current-source plugin/theme option batch (`active_plugins`, `rewrite_rules`,
`theme_mods_twentytwentyfive`) can be exposed.

Focused verification:

```sh
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Plan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Test.php
$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next219.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next219.php
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext219Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 94 assertions, 0 failures
$ php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next219.php
application-trigger-recursive-view-returning-current-source-next219 self-test passed
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this new test file (`+94`, from `106763` to `106857`). Mapped upstream coverage
is unchanged at `624 / 1589`; this is additional current-source PHP behavior
over already mapped trigger/view/RETURNING surfaces, not a newly hydrated Tcl
inventory unit.

Dependency closure: no new support component is needed. The implementation
reuses the lane-local recursive view-trigger RETURNING, current-source
provenance, and row-array projection primitives.

Non-overlap: this avoids accepted next217 provenance, next212 yield receipts,
next210 ordered sequence, next211 source seal, DML RETURNING conflicts,
row-value RETURNING savepoints, schema reparse trigger/view DDL, WAL/VFS,
JSON table, planner, encoding, and B-tree clusters. The new behavior is
specifically next-source RETURNING reset admission before exposing a following
current-source recursive view-trigger generation.

Next task: wire the reset-admission fence into the broader parser/executor
statement lifecycle once native trigger bytecode owns cursor reset directly.
