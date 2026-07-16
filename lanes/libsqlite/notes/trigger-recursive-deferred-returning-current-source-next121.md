# trigger-recursive-deferred-returning-current-source-next121

Adds `SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan`, a bounded current-source behavior helper for the boundary where a top-level `UPDATE ... RETURNING` row is yielded from the current source, recursive triggers update additional rows, and a deferred foreign-key check decides whether the next source commits, blocks, or rolls back to the savepoint.

The new behavior is distinct from accepted next111 rollback suppression: next121 records the RETURNING source token and attempted trigger RETURNING stream separately, then proves rollback keeps the next source at the current token while commit/blocked paths advance to the next token.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNext121Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-deferred-returning-current-source-next121.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNext121Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-deferred-returning-current-source-next121.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused TestRunner result: `1 test files, 55 assertions, 0 failures`.

Application smoke: `application-trigger-recursive-deferred-returning-current-source-next121 self-test passed`.

Status delta: `lane-status.json` `phpPass` increases by the 55 verified focused assertions, from `46412` to `46467`. Mapped upstream coverage is unchanged because this is PHP behavior coverage over an already mapped trigger/FK/RETURNING family, not a newly hydrated Tcl inventory unit.

Non-overlap: avoids accepted recursive RETURNING savepoint rollback, next111 recursive RETURNING deferred FK rollback suppression, deferred FK cascade triggers, DML trigger RETURNING conflict handling, schema trigger reparse, row-value RETURNING, savepoint image rollback, WAL byte truncation, VFS savepoint rollback, and other accepted storage/JSON/planner clusters. The new surface is current-source attribution for RETURNING rows before deferred FK next-source resolution.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array trigger, RETURNING projection, deferred FK, savepoint, and source-token concepts only.

Next task: wire this current-source attribution into broader parser-level DML execution only if the executor can share the same deferred FK commit boundary without another standalone trigger wrapper.
