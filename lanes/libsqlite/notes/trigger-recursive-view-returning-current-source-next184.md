# Trigger Recursive View RETURNING Current Source Next184

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` cursors where a next view/trigger source is admitted only after the
current-source checkpoint handoff is acknowledged.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Plan`.
It builds on accepted next177 resume tokens and next181 checkpoint visibility
without changing either behavior. Current-source checkpoints are filtered to
the current generation, acknowledged with a handoff token, and only then may
the already-admitted next-source `RETURNING` rows become visible. Missing,
unexpected, or mismatched handoff acknowledgements keep the next source held.

Application path:
`application-trigger-recursive-view-returning-current-source-next184.php` models a
copied `wp_options` import through a recursive view trigger. The current
`RETURNING` cursor drains and checkpoints first; the next plugin/source rows
are exposed only after the current checkpoint tokens are acknowledged.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next184.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext184Test.php`
  - `1 test files, 82 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next184.php`
  - `application-trigger-recursive-view-returning-current-source-next184 self-test passed`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: `phpPass +82`, from `86745` to `86827`. Mapped upstream
coverage remains unchanged; this is current-source PHP behavior over already
mapped trigger/view/RETURNING cursor and checkpoint surfaces.

Non-overlap: avoids accepted next172 source pinning, next177 resume-token
admission, next180 source snapshots, next181 checkpoint visibility, next106
DML trigger RETURNING conflicts, deferred FK cascade triggers, schema
trigger/view invalidation, row-value RETURNING, VFS/WAL/B-tree/JSON/PRAGMA,
and SELECT planner clusters. The new surface is the handoff acknowledgement
between a drained current-source RETURNING checkpoint set and next-source row
exposure.

Dependency closure: no new support component is needed. The slice reuses the
lane-local recursive view trigger RETURNING cursor, checkpoint, and
current/next source metadata.

Next task: wire the handoff acknowledgement into the parser-level trigger
executor once native view-trigger bytecode owns cursor checkpoint lifecycle
directly.
