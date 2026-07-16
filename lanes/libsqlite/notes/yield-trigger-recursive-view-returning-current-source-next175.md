# Trigger Recursive View RETURNING Current Source Next175

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext175Plan`.
- Models an `INSTEAD OF` recursive view trigger with RETURNING pages where the
  current-source cursor must drain before a savepoint release can expose queued
  next-source RETURNING pages.
- Covers hold, release, rollback, stale resume-token, partial-drain, zero-drain,
  paging, and validation paths.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext175Test.php`
  - `1 test files, 66 assertions, 0 failures`
  - `66` PASS lines

## Non-Overlap

- Avoids accepted next169 through next173 recursive view RETURNING clusters:
  nested current-source drain, cursor page exhaustion, staged next-source
  admission, and source resume-token checks.
- This slice adds the savepoint action layer around those existing current-source
  fences: release exposes next-source pages only after current cursor exhaustion,
  while rollback restarts from the current-source savepoint image.

## Dependency Closure

- No new support component needed.
- Reuses native PHP recursive view RETURNING current-source cursor and savepoint
  modeling.
