# trigger-recursive-view-returning-current-source-next167

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows across a current-source/next-source boundary.

What changed:

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext167Plan`, which
  layers a paged current-source RETURNING drain fence on the existing recursive
  view-trigger executor.
- Current-source RETURNING pages are exposed as visible yield pages while
  attempted next-source pages remain blocked unless the caller explicitly
  admits the next source after the current drain completes.
- The plan records source signatures, cursor names, page counts, trigger
  source tokens, and blocked/admitted next-source pages so stale view-trigger
  RETURNING rows cannot leak into a Application import preview.

Focused verification:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext167Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures
```

Application smoke:

```sh
$ php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next167.php --self-test
application-trigger-recursive-view-returning-current-source-next167 self-test passed
```

Non-overlap:

This avoids accepted next164 recursive view RETURNING execution by adding the
separate current-source RETURNING page-drain/admission fence. It also avoids
accepted recursive UPSERT RETURNING, deferred FK trigger, view-trigger
savepoint, schema view/trigger reparse, row-value RETURNING, WAL/pager,
B-tree, JSON, PRAGMA, planner, and encoding clusters.

Dependency closure:

No new support component is needed. The slice reuses the lane-local recursive
view-trigger RETURNING executor and adds bounded native PHP cursor/page
metadata only.
