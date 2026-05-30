# Trigger Recursive View RETURNING Current Source Next162

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext162Plan`, which composes the accepted next160 recursive view `RETURNING` barrier with a FIFO queue for two staged next-source trigger yields.
- The current-source `RETURNING` rows remain statement-visible while both next-source attempts are recorded but suppressed.
- Releasing one staged source admits only the first next generation; releasing both admits the second generation after the first, preserving source order and preventing a later next-source view/trigger cookie from leaking early.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext162Test.php`
  - `1 test files, 62 assertions, 0 failures`
  - `62` PASS lines
- Application smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next162.php`
- Syntax/diff checks: php-lint for changed PHP files and `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +62`, from `72664` to `72726`. Mapped upstream coverage remains `609 / 1589`; this extends focused PHP trigger/current-source coverage without claiming a fresh upstream manifest row.

## Non-Overlap

Avoids accepted next160 single current-source barrier behavior by adding a distinct multi-yield next-source queue and FIFO release order. It does not touch schema reparse, deferred FK trigger handling, DML conflict handling, row-value RETURNING, WAL/pager, B-tree, JSON, VFS, or encoding clusters.

## Dependency Closure

No new support component is needed. The slice reuses native PHP recursive view `RETURNING` execution, source-generation barriers, and savepoint current-source modeling under `lanes/libsqlite/src`.
