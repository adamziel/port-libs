# Row-value conflict UPSERT savepoint current-source next136

Status: focused PHP behavior growth for `rowvalue-conflict-upsert-savepoint-current-source-next136`.

This slice adds `SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan`, a bounded current-source diagnostic around the existing row-value UPSERT savepoint executor. It covers the SQLite edge where `INSERT ... ON CONFLICT (...) DO UPDATE SET (...) = (...)` changes the composite conflict-key image, later rows in the same savepoint must resolve conflicts against that moved current source, and a later duplicate composite key rolls the savepoint back to the original table image.

Application smoke: `application-rowvalue-conflict-upsert-savepoint-current-source-next136.php` models a copied `wp_options` import that moves `(blog_id, option_name)` from `siteurl` to `plugin_cache`, updates the moved key on the next UPSERT, then rejects a duplicate `theme_mods` key and restores the savepoint image.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteRowValueConflictUpsertSavepointCurrentSourceNext136Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueConflictUpsertSavepointCurrentSourceNext136Test.php

php -l lanes/libsqlite/examples/application-rowvalue-conflict-upsert-savepoint-current-source-next136.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-conflict-upsert-savepoint-current-source-next136.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueConflictUpsertSavepointCurrentSourceNext136Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-conflict-upsert-savepoint-current-source-next136.php
application-rowvalue-conflict-upsert-savepoint-current-source-next136 self-test passed

git diff --check -- lanes/libsqlite
<no output>
```

Dashboard delta: `phpPass` moves from `57457` to `57519` from 62 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is fresh focused PHP behavior over already mapped row-value UPSERT/savepoint primitives rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted row-value savepoint UPSERT next131 by adding the narrower conflict-key movement/current-source boundary rather than another non-key row-value assignment. It also avoids accepted window row-value UPSERT, row-value IS/savepoint, trigger UPSERT RETURNING savepoint, RETURNING failure savepoint, WAL/pager savepoint, VFS writer/sync/lock, JSON table, B-tree, PRAGMA, planner, and schema reparse clusters.

Dependency closure: no new support component is needed. The slice reuses lane-local native PHP row-value UPSERT parsing/execution and savepoint current-source reporting.

Next task: continue with a distinct SQL executor/planner or storage behavior gap; avoid another row-value UPSERT savepoint wrapper unless it applies a separate SQLite conflict-resolution rule.
