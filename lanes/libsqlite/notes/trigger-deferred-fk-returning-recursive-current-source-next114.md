# trigger-deferred-fk-returning-recursive-current-source-next114

Status: focused PHP behavior growth for recursive trigger-enqueued DML with
RETURNING and deferred foreign-key checks.

This slice adds
`SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan`. It models
the SQLite ordering needed by copied Application imports:

- the top-level parent UPDATE emits its RETURNING image before recursive
  AFTER UPDATE trigger work is drained;
- recursive triggers enqueue additional current-source parent updates while
  `recursive_triggers` is enabled;
- ON UPDATE CASCADE child rows follow each current parent key movement;
- audit trigger rows can introduce deferred FK violations that remain visible
  in statement yield metadata but fail only at commit;
- disabling recursive triggers preserves the top-level statement and suppresses
  queued recursive updates.

Application path:
`application-trigger-deferred-fk-returning-recursive-current-source-next114.php`
models copied `wp_posts` / `wp_postmeta` import rekeying. The smoke proves that
RETURNING rows for the rekeyed parent are yielded before recursive trigger
drain, while deferred audit rows are checked at commit.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNext114Test.php
php -l lanes/libsqlite/examples/application-trigger-deferred-fk-returning-recursive-current-source-next114.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNext114Test.php
php lanes/libsqlite/examples/application-trigger-deferred-fk-returning-recursive-current-source-next114.php --self-test
```

Focused result:

```text
1 test files, 62 assertions, 0 failures
application-trigger-deferred-fk-returning-recursive-current-source-next114 self-test passed
```

Dashboard delta: `phpPass` moves from 43574 to 43636 in this worktree (+62
focused PASS lines). `benchmarkDenominator.mapped` remains 604 / 1589; this is
additional current-source PHP behavior over existing trigger/FK/RETURNING
coverage, not a newly hydrated upstream Tcl inventory row.

Non-overlap: avoids accepted batch106 DML trigger RETURNING conflict handling,
accepted deferred FK cascade trigger coverage, accepted savepoint-trigger
rollback, accepted recursive trigger/savepoint/UPSERT helpers, accepted
RETURNING conflict ordering, and all VFS/WAL/B-tree/JSON/PRAGMA current-source
surfaces. The new surface is their unhandled composition: recursive
trigger-generated parent UPDATE RETURNING rows with deferred FK validation at
commit and current-source/next-source yield metadata.

Dependency closure: no new support component is needed. The slice is a bounded
native PHP row-array executor that reuses lane-local trigger, RETURNING, and
foreign-key semantics; no ext/sqlite, upstream shell-out, live service, or new
shared dependency is introduced.

Next task: wire this ordering into broader parser-level DML execution once the
native trigger bytecode path owns recursive statement queues directly.
