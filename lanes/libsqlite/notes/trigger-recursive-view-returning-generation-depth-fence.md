# Trigger Recursive View RETURNING Generation Depth Fence

Status: consolidated recursive view trigger RETURNING current-generation and recursive-depth fencing under descriptive production/test/example names.

This slice now uses `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentGenerationDepthFence()`. It builds on the accepted child drain model and keeps the later current-source guard: even after the child-drain stage allows next-source publication, the next view generation is held until the current view generation token matches and every required recursive depth has been acknowledged.

WordPress smoke: `wordpress-trigger-recursive-view-returning-generation-depth-fence.php` covers a copied `wp_options` recursive import view where following current rows and recursive child RETURNING rows must publish under the current view generation before next-source `home` / `next_plugin` rows become visible.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentGenerationDepthFenceTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

Expected dashboard movement: none from this cleanup pass; the same 77 focused assertions remain covered after migrating the direct test/example off the numbered entry point. Mapped upstream coverage remains unchanged.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING, child-drain, cursor, and current-source modeling.

Non-overlap: this only renames and migrates the current view generation and recursive depth acknowledgement fence. It avoids receipt fences, child drain behavior, row-value RETURNING, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices.
