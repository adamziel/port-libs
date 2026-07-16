# SQLite trigger recursive view RETURNING current-source next143

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext143Plan` for a Application-style `wp_options` import through an `INSTEAD OF` view trigger where recursive trigger RETURNING rows are yielded before savepoint rollback.
- The current phase can roll back to the saved source image while preserving suppressed RETURNING evidence; the next phase then starts from that saved image and admits only next-source rows.
- Covers default current rollback, both-source release, next rollback, both rollback, non-recursive trigger mode, wildcard RETURNING, ignored recursive conflict handling, and malformed view/source inputs.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext143Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next143.php --self-test`
- Syntax/diff checks: `php -l` for changed PHP files and `git diff --check -- lanes/libsqlite`

## Non-overlap

- Avoids accepted next119 deferred FK RETURNING savepoint behavior, next136 view current-source admission, next139 recursive trigger RETURNING savepoint behavior, and next140 trigger/upsert unique-view behavior by composing a narrower recursive view/current-source two-phase admission path with explicit suppressed/admitted RETURNING streams.

## Dependency Closure

- No new support component is needed. This reuses existing native recursive trigger, savepoint, and view/current-source planning helpers under `lanes/libsqlite/src`.
