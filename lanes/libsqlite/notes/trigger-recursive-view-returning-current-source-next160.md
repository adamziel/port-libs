# Trigger Recursive View RETURNING Current Source Next160

## Behavior

- Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan`, a current-source barrier around recursive view `RETURNING` execution.
- The barrier records current and next source generations, keeps current `RETURNING` rows visible to the statement, and keeps attempted next-source rows suppressed until an explicit release.
- This is intentionally narrower than the accepted next157 recursive view materializer: next160 focuses on statement-visible source-generation boundaries for `RETURNING` rows after the current view trigger drains.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Test.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next160.php`
- Syntax/diff checks: php-lint for changed PHP files and `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses native PHP recursive view `RETURNING` execution and current-source savepoint modeling.

## Non-Overlap

Avoids the accepted batch150 next157 recursive view `RETURNING` materialization by adding a distinct next160 source-generation visibility barrier rather than new recursive row traversal, savepoint image, or trigger recursion behavior.
