# Trigger view RETURNING savepoint recursive current-source next123

This slice adds `SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan`, a bounded coordinator for `INSTEAD OF` view-trigger imports that run a current source batch and then a next source batch against the current result. It preserves statement/yield order, `RETURNING` rows, attempted `RETURNING` diagnostics, and phase-local savepoint rollback metadata.

Application relevance: copied `wp_options` imports often stage through views while plugins attach trigger-based audit rows. This lets the PHP SQLite port model current imported rows becoming the source for the next import phase, while a failing trigger row rolls back only its phase and keeps attempted RETURNING diagnostics visible for migration error reporting.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNext123Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-trigger-view-returning-savepoint-recursive-current-source-next123.php --self-test
application-trigger-view-returning-savepoint-recursive-current-source-next123 self-test passed
```

Dashboard delta: `phpPass` +67, from `47656` to `47723`, from the exact focused PASS-line count. Mapped upstream coverage is unchanged because this is a focused current-source behavior cluster over already mapped trigger, view, RETURNING, and savepoint primitives.

Dependency closure: no new support component is needed; this composes existing native PHP attached-schema trigger yield, view-trigger RETURNING, and savepoint rollback planning.

Non-overlap: avoids accepted trigger RETURNING recursive/upsert next118 and deferred RETURNING savepoint batch118/119 by covering `INSTEAD OF` view-trigger current/next phase composition and phase-local rollback. It also avoids accepted VFS/WAL/B-tree/JSON/planner/encoding clusters named in the current supervisor overrides.
