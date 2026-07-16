# trigger-deferred-returning-view-current-source-next127

Adds `SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan`, a bounded
current-source behavior helper for `INSTEAD OF` view triggers whose attempted
`RETURNING` rows cross current/next source phases before a deferred foreign-key
release check decides whether the next-source rows are admitted, blocked, or
rolled back to the current source.

The slice reuses the accepted view-trigger savepoint executor and adds the
missing release barrier: current and next attempted `RETURNING` streams are
tagged with source tokens, deferred FK violations are reported at release, and
rollback-on-violation restores the current source while preserving attempted
row evidence.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningViewCurrentSourceNext127Test.php
```

Result: `1 test files, 62 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-trigger-deferred-returning-view-current-source-next127.php --self-test
```

Result: `application-trigger-deferred-returning-view-current-source-next127 self-test passed`.

Non-overlap: avoids accepted next125 trigger deferred recursive RETURNING,
accepted next123 view-trigger savepoint recursion, schema ALTER/view-trigger
reparse next125, row-value RETURNING, DML trigger conflict, deferred FK cascade,
and accepted WAL/VFS savepoint/rollback clusters. The new surface is the
deferred FK release barrier for view-trigger `RETURNING` rows with current/next
source attribution.

Dependency closure: no new support component is needed. The slice reuses
lane-local schema catalog, view-trigger yield, savepoint, RETURNING projection,
and row-array FK validation helpers only.
