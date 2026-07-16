# Consolidate Trigger Recursive View RETURNING Source Generation Barrier

## Behavior

- Consolidates the source-generation barrier into the canonical `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan` entrypoint `executeSourceGenerationBarrier()`.
- The barrier records current and next source generations, keeps current `RETURNING` rows visible to the statement, and keeps attempted next-source rows suppressed until an explicit release.
- This cleanup removes the numbered public entrypoint/helper names for the source-generation barrier while preserving statement-visible source-generation behavior after the current view trigger drains.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceGenerationBarrierTest.php`
- Example smoke: `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-source-generation-barrier.php`
- Syntax/diff checks: php-lint for changed PHP files and `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses native PHP recursive view `RETURNING` execution and current-source savepoint modeling.

## Non-Overlap

Avoids the accepted recursive view `RETURNING` materialization behavior by changing only the source-generation barrier names and direct coverage paths; no recursive row traversal, savepoint image, or trigger recursion behavior changes.
