# yield-sqlite-jsonb-generated-covering-delete-current-next52

Behavior slice: JSONB generated-column covering `DELETE` planning for current rows.

- Added `SQLiteJsonbPatchGeneratedIndexPlan::planDeleteCurrentCoveringTable()` for bounded current-row deletes that:
  - choose the same generated JSONB patch covering-index planner used by SELECT/UPSERT lookup paths;
  - emit delete-current entries for every active generated-column index entry on deleted rows;
  - preserve inactive partial-index rows when the current row is outside the partial predicate;
  - return `DELETE ... RETURNING`-style rows from covering index payloads, including generated JSONB values;
  - report non-covering chosen plans as skipped covering returns rather than pretending a table fetch is unnecessary.
- Added focused tests in `SQLiteJsonbGeneratedCoveringDeleteCurrentNext52Test.php`.
- Added Application smoke `application-jsonb-generated-covering-delete-current-next52.php` for copied `wp_options` plugin-setting cleanup.

Non-overlap: this does not repeat accepted JSONB generated partial UPSERT, JSONB table upsert covering, expression-index range costs, JSON hidden/visible constraints, JSON table SELECT sources/cursors, or B-tree/WAL/VFS accepted clusters. The new surface is current-row `DELETE` maintenance over generated JSONB covering indexes.

Dependency closure: no new support component is required. The slice reuses existing native PHP JSONB, JSON patch/extract, generated-index planner, and index predicate helpers.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCoveringDeleteCurrentNext52Test.php
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines
1 test files, 67 assertions, 0 failures

$ php lanes/libsqlite/examples/application-jsonb-generated-covering-delete-current-next52.php
application-jsonb-generated-covering-delete-current-next52 self-test passed

$ php -l lanes/libsqlite/src/SQLiteJsonbPatchGeneratedIndexPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonbPatchGeneratedIndexPlan.php
$ php -l lanes/libsqlite/tests/SQLiteJsonbGeneratedCoveringDeleteCurrentNext52Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonbGeneratedCoveringDeleteCurrentNext52Test.php
$ php -l lanes/libsqlite/examples/application-jsonb-generated-covering-delete-current-next52.php
No syntax errors detected in lanes/libsqlite/examples/application-jsonb-generated-covering-delete-current-next52.php

$ php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok
$ git diff --check -- lanes/libsqlite
no output
```
