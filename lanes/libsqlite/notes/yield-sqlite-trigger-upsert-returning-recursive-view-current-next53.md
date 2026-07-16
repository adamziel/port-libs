# yield-sqlite-trigger-upsert-returning-recursive-view-current-next53

- Added `SQLiteTriggerUpsertReturningRecursiveViewPlan` for the current/next boundary where an `INSTEAD OF` recursive view UPSERT emits `RETURNING` rows only for the statement's current rows while AFTER triggers enqueue next recursive UPSERT rows.
- Added `SQLiteTriggerUpsertReturningRecursiveViewCurrentNext53Test.php` with 62 focused PASS cases covering update/insert statement rows, current-row RETURNING projections, recursive next-row suppression from RETURNING, trigger source attribution, rollback-to-savepoint diagnostics, recursive trigger disablement, and malformed projection/input guards.
- Added `application-trigger-upsert-returning-recursive-view-current-next53.php` as the Application smoke for copied `wp_options` import views that update current option rows and recursively create plugin child option rows without exposing child rows through statement `RETURNING`.
- Non-overlap: avoids accepted batch49 view UPSERT savepoint and recursive-view RETURNING surfaces by adding the missing current-row versus next-recursive-row trace over trigger-generated UPSERT rows. It does not repeat accepted JSON table, VFS, WAL, B-tree page move, expression ORDER BY, SELECT subquery, or Unicode GLOB clusters.
- Dependency closure: no new support component is needed; this reuses the existing lane-local recursive view UPSERT and trigger/FK/savepoint planners.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveViewCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteTriggerUpsertReturningRecursiveViewPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerUpsertReturningRecursiveViewPlan.php

php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveViewCurrentNext53Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveViewCurrentNext53Test.php

php -l lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-view-current-next53.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-view-current-next53.php

php lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-view-current-next53.php
Outputs current-row RETURNING, next-recursive row trace, parent names, and dependency tags for a copied wp_options import view.

git diff --check -- lanes/libsqlite
No output.
```
